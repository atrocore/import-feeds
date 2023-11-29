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

use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ImportJob extends Base
{
    protected bool $cacheable = false;

    public function getImportJobsViaScope(string $scope): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->distinct()
            ->select('ij.id, ij.name')
            ->from('import_job_log', 'ijl')
            ->leftJoin('ijl', 'import_job', 'ij', 'ij.id=ijl.import_job_id AND ij.deleted=:false')
            ->where('ijl.entity_name=:entityName')
            ->andWhere('ijl.deleted=:false')
            ->andWhere('ij.id IS NOT NULL')
            ->setParameter('entityName', $scope)
            ->setParameter('false', false, ParameterType::BOOLEAN)
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
        if ($entity->isAttributeChanged('state') && in_array($entity->get('state'), ['Canceled', 'Pending'])) {
            $qmJob = $this->getQmJob($entity);
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
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $qmJob = $this->getQmJob($entity);
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
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='create' AND import_job_id=import_job.id) created_count")
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='update' AND import_job_id=import_job.id) updated_count")
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='delete' AND import_job_id=import_job.id) deleted_count")
            ->addSelect("(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='error' AND import_job_id=import_job.id) errors_count")
            ->from('import_job')
            ->where('id IN (:ids)')
            ->andWhere('deleted=:false')
            ->setParameter('ids', $ids, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        $res = [];
        foreach ($data as $v) {
            $res[$v['id']] = $v;
        }

        return $res;
    }

    public function getQmJob(Entity $importJob): ?Entity
    {
        if (!empty($importJob->get('queueItemId'))) {
            return $this->getEntityManager()->getRepository('QueueItem')->get($importJob->get('queueItemId'));
        }
        return null;
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
