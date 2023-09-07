<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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
            if (in_array($entity->get('state'), ['Running', 'Pending'])) {
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
        if ($entity->isAttributeChanged('state') &&  in_array($entity->get('state'),['Canceled','Pending'])) {
            $qmJob = $this->getQmJob($entity->get('id'));
            if (!empty($qmJob)) {
                if ($entity->get('state') === 'Pending' && in_array($qmJob->get('status'), ['Success', 'Failed', 'Canceled'])) {
                    $this->toPendingQmJob($qmJob);
                }
                if ($entity->get('state') === 'Canceled') {
                    $this->cancelQmJob($qmJob);
                }
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

        if (!empty($attachment = $entity->get('attachment'))) {
            $jobWithSuchAttachment = $this->where(['id!=' => $entity->get('id'), 'attachmentId' => $attachment->get('id')])->findOne();
            if (empty($jobWithSuchAttachment)) {
                $importFeedWithSuchAttachment = $this->where(['fileId' => $attachment->get('id')])->findOne();
                if (empty($importFeedWithSuchAttachment)) {
                    $this->getEntityManager()->removeEntity($attachment);
                    if (!empty($convertedFile = $entity->get('convertedFile'))) {
                        $this->getEntityManager()->removeEntity($convertedFile);
                    }
                }
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

    public function getQmJob(string $id): ?Entity
    {
        return $this->getEntityManager()->getRepository('QueueItem')->where(['data*' => '%"importJobId":"' . $id . '"%'])->findOne();
    }

    protected function toPendingQmJob(Entity $qmJob): void
    {
        $this->getInjection('queueManager')->tryAgain($qmJob->get('id'));
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
        $this->addDependency('queueManager');
    }
}
