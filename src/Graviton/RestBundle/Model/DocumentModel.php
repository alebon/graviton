<?php

namespace Graviton\RestBundle\Model;

use Doctrine\Common\Persistence\ObjectRepository;
use Knp\Component\Pager\Paginator;

/**
 * Use doctrine odm as backend
 *
 * @category GravitonRestBundle
 * @package  Graviton
 * @author   Lucas Bickel <lucas.bickel@swisscom.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.com
 */
class DocumentModel implements ModelInterface
{
    /**
     * @var ObjectRepository
     */
    private $repository;

    /**
     * @var Paginator
     */
    private $paginator;

    /**
     * create new app model
     *
     * @param ObjectRepository $countries Repository of countries
     *
     * @return void
     */
    public function setRepository(ObjectRepository $countries)
    {
        $this->repository = $countries;
    }

    /**
     * get repository instance
     *
     * @return ObjectRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * set paginator
     *
     * @param Paginator $paginator paginator used in collection
     *
     * @return void
     */
    public function setPaginator(Paginator $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $id id of entity to find
     *
     * @return Object
     */
    public function find($id)
    {
        return $this->repository->find($id);
    }

    /**
     * {@inheritDoc}
     *
     * @param Request $request Request object
     *
     * @return array
     */
    public function findAll($request)
    {
        $pagination = $this->paginator->paginate(
            $this->repository->findAll(),
            $request->query->get('page', 1),
            10
        );

        $numPages = (int) ceil($pagination->getTotalItemCount() / $pagination->getItemNumberPerPage());
        if ($numPages > 1) {
            $request->attributes->set('paging', true);
            $request->attributes->set('numPages', $numPages);
        }

        return $pagination->getItems();
    }

    /**
     * {@inheritDoc}
     *
     * @param object $entity entityy to insert
     *
     * @return Object
     */
    public function insertRecord($entity)
    {
        $dm = $this->repository->getDocumentManager();
        $dm->persist($entity);
        $dm->flush();

        return $this->find($entity->getId());
    }

    /**
     * {@inheritDoc}
     *
     * @param string $id     id of entity to update
     * @param Object $entity new enetity
     *
     * @return Object
     */
    public function updateRecord($id, $entity)
    {
        $dm = $this->repository->getDocumentManager();
        $dm->persist($entity);
        $dm->flush();

        return $entity;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $id id of entity to delete
     *
     * @return null|Object
     */
    public function deleteRecord($id)
    {
        $dm = $this->repository->getDocumentManager();
        $entity = $this->find($id);

        $return = $entity;
        if ($entity) {
            $dm->remove($entity);
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
     * to newly made documents.
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
        return 'graviton.core';
    }
}