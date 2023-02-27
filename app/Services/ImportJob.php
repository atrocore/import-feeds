<?php
/*
 * Import Feeds
 * Free Extension
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Import\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\FilePathBuilder;
use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ImportJob extends Base
{
    protected $mandatorySelectAttributeList = ['message'];

    public function generateConvertedFile(string $jobId): array
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $jobId);
        if (empty($importJob)) {
            throw new BadRequest('No such ImportJob.');
        }

        $qmJob = $this->getEntityManager()->getRepository('ImportJob')->getQmJob($importJob->get('id'));
        if (empty($qmJob)) {
            throw new BadRequest("QueueItem for ImportJob '{$importJob->get('id')}' does not exist.");
        }

        // prepare job data
        $jobData = json_decode(json_encode($qmJob->get('data')), true);

        /** @var \Import\Services\ImportTypeSimple $importService */
        $importService = $this->getServiceFactory()->create('ImportTypeSimple');

        /** @var \Espo\Repositories\Attachment $attachmentRepository */
        $attachmentRepository = $this->getEntityManager()->getRepository('Attachment');

        // prepare converted file attachment
        $convertedFileAttachment = $attachmentRepository->get();
        $convertedFileAttachment->set([
            'name'            => str_replace(' ', '_', $importJob->get('name')) . '.csv',
            'role'            => 'Attachment',
            'field'           => 'convertedFile',
            'relatedType'     => 'ImportJob',
            'relatedId'       => $importJob->get('id'),
            'storage'         => 'UploadDir',
            'type'            => 'text/csv',
            'storageFilePath' => $this->getInjection('container')->get('filePathBuilder')->createPath(FilePathBuilder::UPLOAD),
        ]);

        // create dir for converted file
        $convertedFileDirPath = trim($this->getConfig()->get('filesPath', 'upload/files'), '/') . '/' . $convertedFileAttachment->get('storageFilePath');
        while (!file_exists($convertedFileDirPath)) {
            mkdir($convertedFileDirPath, 0777, true);
            usleep(100);
        }

        // create converted file
        $convertedFile = fopen($convertedFileDirPath . '/' . $convertedFileAttachment->get('name'), 'w');

        while (!empty($inputData = $importService->getInputData($jobData))) {
            while (!empty($inputData)) {
                $row = array_shift($inputData);

                // push header to converted file
                if (empty($convertedFileHeaderPushed)) {
                    fputcsv($convertedFile, array_keys($row));
                    $convertedFileHeaderPushed = true;
                }

                // push row to converted file
                fputcsv($convertedFile, array_values($row));
            }
        }

        // save converted file attachment
        fclose($convertedFile);
        $convertedFileAttachment->set('size', \filesize($attachmentRepository->getFilePath($convertedFileAttachment)));
        $this->getEntityManager()->saveEntity($convertedFileAttachment);

        // set converted file attachment to import job
        $importJob->set('convertedFileId', $convertedFileAttachment->get('id'));
        $this->getEntityManager()->saveEntity($importJob);

        return $convertedFileAttachment->toArray();
    }

    public function getImportJobsViaScope(string $scope): array
    {
        return $this
            ->getEntityManager()
            ->getRepository('ImportJob')
            ->getImportJobsViaScope($scope);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $data = $this->getRepository()->getJobsCounts(array_column($collection->toArray(), 'id'));

        foreach ($collection as $entity) {
            $entity->set('createdCount', $data[$entity->get('id')]['createdCount']);
            $entity->set('updatedCount', $data[$entity->get('id')]['updatedCount']);
            $entity->set('deletedCount', $data[$entity->get('id')]['deletedCount']);
            $entity->set('errorsCount', $data[$entity->get('id')]['errorsCount']);
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!$entity->has('createdCount')) {
            $entity->set('createdCount', $this->getLogCount('create', (string)$entity->get('id')));
        }
        if (!$entity->has('updatedCount')) {
            $entity->set('updatedCount', $this->getLogCount('update', (string)$entity->get('id')));
        }
        if (!$entity->has('deletedCount')) {
            $entity->set('deletedCount', $this->getLogCount('delete', (string)$entity->get('id')));
        }
        if (!$entity->has('errorsCount')) {
            $entity->set('errorsCount', $this->getLogCount('error', (string)$entity->get('id')));
        }
    }

    protected function getLogCount(string $type, string $importJobId): int
    {
        return $this
            ->getEntityManager()
            ->getRepository('ImportJobLog')
            ->where(['importJobId' => $importJobId, 'type' => $type])
            ->count();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
