<?php
/**
 * Use doctrine odm as backend
 */

namespace Graviton\RestBundle\Model;

use Doctrine\Common\Persistence\ObjectRepository;
use Graviton\SchemaBundle\Model\SchemaModel;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ODM\MongoDB\Query\Builder;
use Graviton\Rql\Visitor\MongoOdm as Visitor;
use Xiag\Rql\Parser\Query;
use Graviton\RestBundle\Model\RecordOriginInterface;
use Graviton\ExceptionBundle\Exception\RecordOriginModifiedException;

/**
 * Use doctrine odm as backend
 *
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class DocumentModel extends SchemaModel implements ModelInterface
{
    /**
     * @var string
     */
    protected $description;
    /**
     * @var string[]
     */
    protected $fieldTitles;
    /**
     * @var string[]
     */
    protected $fieldDescriptions;
    /**
     * @var string[]
     */
    protected $requiredFields = array();
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository
     */
    private $repository;

    /**
     * @var Visitor
     */
    private $visitor;

    /**
     * @var array
     */
    protected $notModifiableOriginRecords;

    /**
     * @param Visitor $visitor                    rql query visitor
     * @param array   $notModifiableOriginRecords strings with not modifiable recordOrigin values
     */
    public function __construct(Visitor $visitor, $notModifiableOriginRecords)
    {
        parent::__construct();
        $this->visitor = $visitor;
        $this->notModifiableOriginRecords = $notModifiableOriginRecords;
    }

    /**
     * get repository instance
     *
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * create new app model
     *
     * @param \Doctrine\Common\Persistence\ObjectRepository $repository Repository of countries
     *
     * @return \Graviton\RestBundle\Model\DocumentModel
     */
    public function setRepository(ObjectRepository $repository)
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param \Symfony\Component\HttpFoundation\Request $request Request object
     *
     * @return array
     */
    public function findAll(Request $request)
    {
        $pageNumber = $request->query->get('page', 1);
        $numberPerPage = (int) $request->query->get('perPage', 10);
        $startAt = ($pageNumber - 1) * $numberPerPage;

        /** @var \Doctrine\ODM\MongoDB\Query\Builder $queryBuilder */
        $queryBuilder = $this->repository
            ->createQueryBuilder();

        // *** do we have an RQL expression, do we need to filter data?
        if ($request->attributes->get('hasRql', false)) {
            $queryBuilder = $this->doRqlQuery(
                $queryBuilder,
                $request->attributes->get('rqlQuery')
            );
        } else {
            // @todo [lapistano]: seems the offset is missing for this query.
            /** @var \Doctrine\ODM\MongoDB\Query\Builder $qb */
            $queryBuilder->find($this->repository->getDocumentName());
        }

        // define offset and limit
        if (!array_key_exists('skip', $queryBuilder->getQuery()->getQuery())) {
            $queryBuilder->skip($startAt);
        }

        if (!array_key_exists('limit', $queryBuilder->getQuery()->getQuery())) {
            $queryBuilder->limit($numberPerPage);
        } else {
            $numberPerPage = (int) $queryBuilder->getQuery()->getQuery()['limit'];
        }

        /**
         * add a default sort on id if none was specified earlier
         *
         * not specifying something to sort on leads to very weird cases when fetching references
         */
        if (!array_key_exists('sort', $queryBuilder->getQuery()->getQuery())) {
            $queryBuilder->sort('_id');
        }

        // run query
        $query = $queryBuilder->getQuery();
        $records = array_values($query->execute()->toArray());

        $totalCount = $query->count();
        $numPages = (int) ceil($totalCount / $numberPerPage);
        if ($numPages > 1) {
            $request->attributes->set('paging', true);
            $request->attributes->set('numPages', $numPages);
            $request->attributes->set('perPage', $numberPerPage);
        }

        return $records;
    }

    /**
     * @param \Graviton\I18nBundle\Document\Translatable $entity entityy to insert
     *
     * @return Object
     */
    public function insertRecord($entity)
    {
        $this->checkIfOriginRecord($entity);
        $manager = $this->repository->getDocumentManager();
        $manager->persist($entity);
        $manager->flush($entity);

        return $this->find($entity->getId());
    }

    /**
     * @param string $documentId id of entity to find
     *
     * @return Object
     */
    public function find($documentId)
    {
        return $this->repository->find($documentId);
    }

    /**
     * {@inheritDoc}
     *
     * @param string $documentId id of entity to update
     * @param Object $entity     new entity
     *
     * @return Object
     */
    public function updateRecord($documentId, $entity)
    {
        $manager = $this->repository->getDocumentManager();
        // In both cases the document attribute named originRecord must not be 'core'
        $this->checkIfOriginRecord($entity);
        $this->checkIfOriginRecord($this->find($documentId));
        $entity = $manager->merge($entity);
        $manager->flush();

        return $entity;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $documentId id of entity to delete
     *
     * @return null|Object
     */
    public function deleteRecord($documentId)
    {
        $manager = $this->repository->getDocumentManager();
        $entity = $this->find($documentId);

        $return = $entity;
        if ($entity) {
            $this->checkIfOriginRecord($entity);
            $manager->remove($entity);
            $manager->flush();
            $return = null;
        }

        return $return;
    }

    /**
     * get classname of entity
     *
     * @return string
     */
    public function getEntityClass()
    {
        return $this->repository->getDocumentName();
    }

    /**
     * {@inheritDoc}
     *
     * Currently this is being used to build the route id used for redirecting
     * to newly made documents. It might benefit from having a different name
     * for those purposes.
     *
     * We might use a convention based mapping here:
     * Graviton\CoreBundle\Document\App -> mongodb://graviton_core
     * Graviton\CoreBundle\Entity\Table -> mysql://graviton_core
     *
     * @todo implement this in a more convention based manner
     *
     * @return string
     */
    public function getConnectionName()
    {
        $bundle = strtolower(substr(explode('\\', get_class($this))[1], 0, -6));

        return 'graviton.' . $bundle;
    }

    /**
     * Does the actual query using the RQL Bundle.
     *
     * @param Builder $queryBuilder Doctrine ODM QueryBuilder
     * @param Query   $query        query from parser
     *
     * @return array
     */
    protected function doRqlQuery($queryBuilder, Query $query)
    {
        $this->visitor->setBuilder($queryBuilder);
        return $this->visitor->visit($query);
    }

    /**
     * Checks the recordOrigin attribute of a record and will throw an exception if value is not allowed
     *
     * @param Object $record record
     *
     * @return void
     */
    protected function checkIfOriginRecord($record)
    {
        if ($record instanceof RecordOriginInterface
            && !$record->isRecordOriginModifiable()
        ) {
            $values = $this->notModifiableOriginRecords;
            $originValue = strtolower(trim($record->getRecordOrigin()));

            if (in_array($originValue, $values)) {
                $msg = sprintf("Must not be one of the following keywords: %s", implode(', ', $values));

                throw new RecordOriginModifiedException($msg);
            }
        }
    }
}
