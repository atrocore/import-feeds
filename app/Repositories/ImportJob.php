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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Import\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\Services\Attachment;
use PhpOffice\PhpSpreadsheet\IOFactory as PhpSpreadsheet;

class ImportJob extends Base
{
    public const IMPORT_ERRORS_COLUMN = 'Import Errors';

    public function getImportJobsViaScope(string $scope): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->distinct()
            ->select('ij.id, ij.name')
            ->from('import_job_log', 'ijl')
            ->leftJoin('ijl', 'import_job', 'ij', 'ij.id=ijl.import_job_id AND ij.deleted=0')
            ->where('ijl.entity_name=:entityName')->setParameter('entityName', $scope)
            ->andWhere('ijl.deleted=0')
            ->andWhere('ij.id IS NOT NULL')
            ->fetchAllAssociative();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $importFeed = $entity->get('importFeed');
        if (empty($importFeed)) {
            throw new BadRequest('Import Feed is required.');
        }

        if ($entity->isAttributeChanged('state')) {
            if ($entity->get('state') == 'Running') {
                $entity->set('start', date('Y-m-d H:i:s'));
            } elseif ($entity->get('state') == 'Success') {
                $entity->set('end', date('Y-m-d H:i:s'));
            }
        }

        if ($entity->isAttributeChanged('state') && $entity->get('state') === 'Canceled' && !in_array($entity->getFetched('state'), ['Pending', 'Running'])) {
            throw new BadRequest('Unexpected job state.');
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('state') && $entity->get('state') == 'Success') {
            $this->generateErrorsAttachment($entity);
        }

        if ($entity->isAttributeChanged('state') && $entity->get('state') === 'Canceled') {
            $qmJob = $this->getQmJob($entity->get('id'));
            if (!empty($qmJob)) {
                $this->cancelQmJob($qmJob);
            }
        }

        parent::afterSave($entity, $options);

        $importFeed = $entity->get('importFeed');
        if (!empty($importFeed)) {
            $jobs = $this
                ->where([
                    'importFeedId' => $importFeed->get('id'),
                    'state'        => [
                        'Success',
                        'Failed',
                        'Canceled'
                    ]
                ])
                ->order('createdAt', 'DESC')
                ->limit(2000, 100)
                ->find();
            foreach ($jobs as $job) {
                $this->getEntityManager()->removeEntity($job);
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $qmJob = $this->getQmJob($entity->get('id'));
        if (!empty($qmJob)) {
            $this->cancelQmJob($qmJob);
            $this->getEntityManager()->removeEntity($qmJob);
        }

        $this->getEntityManager()->getRepository('importJobLog')->where(['importJobId' => $entity->get('id')])->removeCollection();

        $attachment = $entity->get('attachment');

        $jobWithSuchAttachment = $this->where(['id!=' => $entity->get('id'), 'attachmentId' => $attachment->get('id')])->findOne();
        if (empty($jobWithSuchAttachment)) {
            $importFeedWithSuchAttachment = $this->where(['fileId' => $attachment->get('id')])->findOne();
            if (empty($importFeedWithSuchAttachment)) {
                $this->getEntityManager()->removeEntity($attachment);
            }
        }

        if (!empty($errorsAttachment = $entity->get('errorsAttachment'))) {
            $this->getEntityManager()->removeEntity($errorsAttachment);
        }

        parent::afterRemove($entity, $options);
    }

    public function getJobsCounts(array $ids): array
    {
        $data = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=0 AND type='create' AND import_job_id=import_job.id) createdCount")
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=0 AND type='update' AND import_job_id=import_job.id) updatedCount")
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=0 AND type='delete' AND import_job_id=import_job.id) deletedCount")
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=0 AND type='error' AND import_job_id=import_job.id) errorsCount")
            ->from('import_job')
            ->where('id IN (:ids)')->setParameter('ids', $ids, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ->andWhere('deleted=0')
            ->fetchAllAssociative();

        $res = [];
        foreach ($data as $v) {
            $res[$v['id']] = $v;
        }

        return $res;
    }

    public function generateErrorsAttachment(Entity $importJob): bool
    {
        $errorLogs = $this
            ->getEntityManager()
            ->getRepository('ImportJobLog')
            ->select(['rowNumber', 'message'])
            ->where([
                'importJobId' => $importJob->get('id'),
                'type'        => 'error'
            ])
            ->find()
            ->toArray();

        if (empty($errorLogs)) {
            return false;
        }

        // get importFeed
        $feed = $importJob->get('importFeed');

        $errorsRowsNumbers = [];

        // add header row if it needs
        if (!empty($feed->getFeedField('isFileHeaderRow')) || $feed->get('type') !== 'simple') {
            $errorsRowsNumbers[1] = self::IMPORT_ERRORS_COLUMN;
        }

        foreach ($errorLogs as $log) {
            $rowNumber = (int)$log['rowNumber'];
            $errorsRowsNumbers[$rowNumber] = $log['message'];
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $importJob->get('attachmentId'));

        $fileParser = $this->getInjection('serviceFactory')->create('ImportFeed')->getFileParser($feed->getFeedField('format'));

        // get file data
        $data = $fileParser->getFileData($attachment, $feed->getDelimiter(), $feed->getEnclosure());

        // collect errors rows
        $errorsRows = [];
        foreach ($data as $k => $row) {
            $key = $k + 1;
            if (isset($errorsRowsNumbers[$key])) {
                $row[] = $errorsRowsNumbers[$key];
                $errorsRows[] = $row;
            }
        }

        /** @var Attachment $attachmentService */
        $attachmentService = $this->getInjection('serviceFactory')->create('Attachment');

        // prepare attachment name
        $nameParts = explode('.', $importJob->get('attachment')->get('name'));
        array_pop($nameParts);
        $name = 'errors-' . implode('.', $nameParts);

        $inputData = new \stdClass();
        $inputData->name = "{$name}.csv";
        $inputData->contents = $this->generateCsvContents($errorsRows, $feed->getDelimiter(), $feed->getEnclosure());
        $inputData->type = 'text/csv';
        $inputData->relatedType = 'ImportJob';
        $inputData->field = 'errorsAttachment';
        $inputData->role = 'Attachment';

        $attachment = $attachmentService->createEntity($inputData);

        // create xlsx
        if ($feed->getFeedField('format') === 'Excel') {
            $filePath = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);
            $cacheDir = 'data/cache';

            Util::createDir($cacheDir);
            $cacheFile = "{$cacheDir}/{$name}.xlsx";

            $reader = PhpSpreadsheet::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setDelimiter($feed->getDelimiter());
            $reader->setEnclosure($feed->getEnclosure());
            $writer = PhpSpreadsheet::createWriter($reader->load($filePath), "Xlsx");
            $writer->save($cacheFile);

            $inputData->name = "{$name}.xlsx";
            $inputData->type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $inputData->contents = file_get_contents($cacheFile);

            // remove csv
            $this->getEntityManager()->removeEntity($attachment);

            // remove cache file
            unlink($cacheFile);

            // create xlsx
            $attachment = $attachmentService->createEntity($inputData);
        }

        $importJob->set('errorsAttachmentId', $attachment->get('id'));
        $this->getEntityManager()->saveEntity($importJob, ['skipAll' => true]);

        return true;
    }

    protected function generateCsvContents($data, $delimiter, $enclosure): string
    {
        // prepare file name
        $fileName = 'data/tmp_import_file.csv';

        // create file
        $fp = fopen($fileName, 'w');
        foreach ($data as $fields) {
            fputcsv($fp, $fields, $delimiter, $enclosure);
        }
        fclose($fp);

        // get contents
        $contents = file_get_contents($fileName);

        // delete file
        unlink($fileName);

        return $contents;
    }

    public function getQmJob(string $id): ?Entity
    {
        return $this->getEntityManager()->getRepository('QueueItem')->where(['data*' => '%"importJobId":"' . $id . '"%'])->findOne();
    }

    protected function cancelQmJob(Entity $qmJob): void
    {
        if (in_array($qmJob->get('status'), ['Pending', 'Running'])) {
            $qmJob->set('status', 'Canceled');
            $this->getEntityManager()->saveEntity($qmJob);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('fileStorageManager');
    }
}
