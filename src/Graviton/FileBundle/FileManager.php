<?php
/**
 * Handles file specific actions
 */

namespace Graviton\FileBundle;

use Gaufrette\File;
use Gaufrette\FileSystem;
use Graviton\ExceptionBundle\Exception\MalformedInputException;
use Graviton\RestBundle\Model\DocumentModel;
use GravitonDyn\FileBundle\Document\File as FileDocument;
use GravitonDyn\FileBundle\Document\FileMetadata;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class FileManager
{
    /**
     * @var FileSystem
     */
    private $fileSystem;

    /**
     * @var FileDocumentFactory
     */
    private $fileDocumentFactory;

    /**
     * FileManager constructor.
     *
     * @param FileSystem          $fileSystem          file system abstraction layer for s3 and more
     * @param FileDocumentFactory $fileDocumentFactory Instance to be used to create action entries.
     */
    public function __construct(FileSystem $fileSystem, FileDocumentFactory $fileDocumentFactory)
    {
        $this->fileSystem = $fileSystem;
        $this->fileDocumentFactory = $fileDocumentFactory;
    }

    /**
     * Indicates whether the file matching the specified key exists
     *
     * @param string $key Identifier to be found
     *
     * @return boolean TRUE if the file exists, FALSE otherwise
     */
    public function has($key)
    {
        return $this->fileSystem->has($key);
    }

    /**
     * Deletes the file matching the specified key
     *
     * @param string $key Identifier to be deleted
     *
     * @throws \RuntimeException when cannot read file
     *
     * @return boolean
     */
    public function delete($key)
    {
        return $this->fileSystem->delete($key);
    }

    /**
     * Reads the content from the file
     *
     * @param  string $key Key of the file
     *
     * @throws \Gaufrette\Exception\FileNotFound when file does not exist
     * @throws \RuntimeException                 when cannot read file
     *
     * @return string
     */
    public function read($key)
    {
        return $this->fileSystem->read($key);
    }

    /**
     * Stores uploaded files to CDN
     *
     * @param Request           $request  Current Http request
     * @param DocumentModel     $model    Model to be used to manage entity
     * @param FileDocument|null $fileData meta information about the file to be stored.
     *
     * @return array
     */
    public function saveFiles(Request $request, DocumentModel $model, FileDocument $fileData = null)
    {
        $inStore = [];
        $files = $this->extractUploadedFiles($request);

        foreach ($files as $key => $fileInfo) {
            /** @var FileDocument $record */
            $record = $this->getRecord($model, $fileData, $request->get('id'));
            $inStore[] = $record->getId();

            /** @var \Gaufrette\File $file */
            $file = $this->saveFile($record->getId(), $fileInfo['content']);
            $actions = (!empty($fileData)) ? $fileData->getMetadata()->getAction()->toArray() : [];
            $links = (!empty($fileData)) ? $fileData->getLinks()->toArray() : [];

            $meta = $this->initOrUpdateMetadata(
                $record,
                $file->getSize(),
                $fileInfo,
                $actions
            );

            $record->setMetadata($meta);
            $record->setLinks($links);
            $model->updateRecord($record->getId(), $record);

            // TODO NOTICE: ONLY UPLOAD OF ONE FILE IS CURRENTLY SUPPORTED
            break;
        }

        return $inStore;
    }

    /**
     * Save or update a file
     *
     * @param string $id   ID of file
     * @param String $data content to save
     *
     * @return File
     *
     * @throws BadRequestHttpException
     */
    public function saveFile($id, $data)
    {
        if (is_resource($data)) {
            throw new BadRequestHttpException('/file does not support storing resources');
        }
        $file = new File($id, $this->fileSystem);
        $file->setContent($data);

        return $file;
    }

    /**
     * Moves uploaded files to tmp directory
     *
     * @param Request $request Current http request
     *
     * @return array
     */
    private function extractUploadedFiles(Request $request)
    {
        $uploadedFiles = [];

        /** @var  $uploadedFile \Symfony\Component\HttpFoundation\File\UploadedFile */
        foreach ($request->files->all() as $field => $uploadedFile) {
            if (0 === $uploadedFile->getError()) {
                $uploadedFiles[$field] = [
                    'data' => [
                        'mimetype' => $uploadedFile->getMimeType(),
                        'filename' => $uploadedFile->getClientOriginalName()
                    ],
                    'content' => file_get_contents($uploadedFile->getPathName())
                ];
            } else {
                throw new UploadException($uploadedFile->getErrorMessage());
            }
        }

        if (empty($uploadedFiles)) {
            $uploadedFiles['upload'] = [
                'data' => [
                    'mimetype' => $request->headers->get('Content-Type'),
                    'filename' => ''
                ],
                'content' => $request->getContent()
            ];
        }

        return $uploadedFiles;
    }

    /**
     * Provides a set up instance of the file document
     *
     * @param DocumentModel     $model    Document model
     * @param FileDocument|null $fileData File information
     * @param string            $id       Alternative Id to be checked
     *
     * @return FileDocument
     */
    private function getRecord(DocumentModel $model, FileDocument $fileData = null, $id = '')
    {
        // does it really exist??
        if (!empty($fileData)) {
            $record = $model->find($fileData->getId());
        } elseif (!empty($id)) {
            $record = $model->find($id);
        }

        if (!empty($record)) {
            // handle missing 'id' field in input to a PUT operation
            // if it is settable on the document, let's set it and move on.. if not, inform the user..
            if ($record->getId() != $id) {
                // try to set it..
                if (is_callable(array($fileData, 'setId'))) {
                    $record->setId($id);
                } else {
                    throw new MalformedInputException('No ID was supplied in the request payload.');
                }
            }

            return $model->updateRecord($id, $record);
        }

        if (!empty($fileData)) {
            $record = $fileData;
        } else {
            $entityClass = $model->getEntityClass();
            $record = new $entityClass();
        }

        return $model->insertRecord($record);
    }

    /**
     * Provides a set up FileMetaData instance
     *
     * @param FileDocument $file     Document to be used
     * @param integer      $fileSize Size of the uploaded file
     * @param array        $fileInfo Additional info about the file
     * @param array        $actions  List of actions to trigger workers
     *
     * @return FileMetadata
     */
    private function initOrUpdateMetadata(FileDocument $file, $fileSize, array $fileInfo, array $actions)
    {
        $meta = $file->getMetadata();
        if (!empty($meta)) {
            $meta
                ->setAction($actions)
                ->setSize((int) $fileSize)
                ->setMime($fileInfo['data']['mimetype'])
                ->setModificationdate(new \DateTime());
        } else {
            // update record with file metadata
            $meta = $this->fileDocumentFactory->initiateFileMataData(
                $file->getId(),
                (int) $fileSize,
                $fileInfo['data']['filename'],
                $fileInfo['data']['mimetype'],
                $actions
            );
        }

        return $meta;
    }

    /**
     * Extracts different information sent in the request content.
     *
     * @param Request $request Current http request
     *
     * @return array
     */
    public function extractDataFromRequestContent(Request $request)
    {
        // split content
        $contentType = $request->headers->get('Content-Type');
        list(, $boundary) = explode('; boundary=', $contentType);

        // fix boundary dash count
        $boundary = '--' . $boundary;

        $content = $request->getContent();
        $contentBlocks = explode($boundary, $content, -1);
        $metadataInfo = '';
        $fileInfo = '';

        // determine content blocks usage
        foreach ($contentBlocks as $contentBlock) {
            if (empty($contentBlock)) {
                continue;
            }
            if (40 === strpos($contentBlock, 'upload')) {
                $fileInfo = $contentBlock;
                continue;
            }
            if (40 === strpos($contentBlock, 'metadata')) {
                $metadataInfo = $contentBlock;
                continue;
            }
        }

        $attributes = array_merge(
            $request->attributes->all(),
            $this->extractMetaDataFromContent($metadataInfo)
        );
        $files = $this->extractFileFromContent($fileInfo);

        return ['files' => $files, 'attributes' => $attributes];
    }

    /**
     * Extracts meta information from request content.
     *
     * @param string $metadataInfoString Information about metadata information
     *
     * @return array
     */
    private function extractMetaDataFromContent($metadataInfoString)
    {
        if (empty($metadataInfoString)) {
            return ['metadata' => '{}'];
        }

        $metadataInfo = explode("\r\n", ltrim($metadataInfoString));
        return ['metadata' => $metadataInfo[2]];
    }

    /**
     * Extracts file data from request content
     *
     * @param string $fileInfoString Information about uploaded files.
     *
     * @return array
     */
    private function extractFileFromContent($fileInfoString)
    {
        if (empty($fileInfoString)) {
            return null;
        }

        $fileInfo = explode("\r\n\r\n", ltrim($fileInfoString), 2);

        // write content to file ("upload_tmp_dir" || sys_get_temp_dir() )
        preg_match('@name=\"([^"]*)\";\sfilename=\"([^"]*)\"\s*Content-Type:\s([^"]*)@', $fileInfo[0], $matches);
        $originalName = $matches[2];
        $dir = ini_get('upload_tmp_dir');
        $dir = (empty($dir)) ? sys_get_temp_dir() : $dir;
        $file = $dir . '/' . $originalName;
        $fileContent = substr($fileInfo[1], 0, -2);

        // create file
        touch($file);
        $size = file_put_contents($file, $fileContent, LOCK_EX);

        $files = [
            $matches[1] => new UploadedFile(
                $file,
                $originalName,
                $matches[3],
                $size
            )
        ];

        return $files;
    }
}
