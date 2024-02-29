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
use Espo\ORM\EntityCollection;

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

        if (!$entity->isNew() && $entity->isAttributeChanged('state')) {
            $this->updateParentState($entity);
        }
    }

    public function updateParentState(Entity $entity): void
    {
        if (empty($entity->get('parentId')) || empty($parent = $entity->get('parent'))) {
            return;
        }

        if ($entity->get('state') === 'Running' && $parent->get('state') !== 'Running') {
            $parent->set('state', 'Running');
            $this->getEntityManager()->saveEntity($parent);
            return;
        }

        if (in_array($entity->get('state'), ['Success', 'Failed'])) {
            $children = $this->getConnection()->createQueryBuilder()
                ->select('id, state')
                ->from('import_job')
                ->where('parent_id = :id')
                ->andWhere('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('id', $parent->get('id'))
                ->fetchAllAssociative();

            $states = array_unique(array_column($children, 'state'));

            if (in_array('Canceled', $states) && count($states) === 1) {
                $parent->set('state', 'Canceled');
                $this->getEntityManager()->saveEntity($parent);
                return;
            }

            // unset Canceled from data array
            $key = array_search('Canceled', $states);
            if ($key !== false) {
                unset($states[$key]);
            }

            if (in_array('Failed', $states) && count($states) === 1) {
                $parent->set('state', 'Failed');
                $this->getEntityManager()->saveEntity($parent);
                return;
            }

            if (in_array('Success', $states) && count($states) === 1) {
                $parent->set('state', 'Success');
                $this->getEntityManager()->saveEntity($parent);
                return;
            }

            if (in_array('Failed', $states) && in_array('Success', $states) && count($states) === 2) {
                $parent->set('state', 'Success');
                $this->getEntityManager()->saveEntity($parent);
                return;
            }

            if (in_array('Failed', $states) && in_array('Success', $states) && count($states) === 2) {
                $parent->set('state', 'Success');
                $this->getEntityManager()->saveEntity($parent);
                return;
            }
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $qmJob = $this->getQmJob($entity);
        if (!empty($qmJob)) {
            $this->cancelQmJob($qmJob);
            $this->getEntityManager()->removeEntity($qmJob);
        }

        // delete import logs
        while (true) {
            $logsToDelete = $this->getEntityManager()->getRepository('importJobLog')
                ->where(['importJobId' => $entity->get('id')])
                ->limit(0, 4000)
                ->find();

            if (empty($logsToDelete[0])) {
                break;
            }

            foreach ($logsToDelete as $log) {
                $log->removeEntity();
            }
        }

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

    public function getJobsCounts(EntityCollection $collection): array
    {
        if (empty($collection[0])) {
            return [];
        }

        $ids = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('import_job')
            ->where('id IN (:ids)')
            ->andWhere('deleted=:false')
            ->andWhere('created_count IS NULL OR updated_count IS NULL OR deleted_count IS NULL OR errors_count IS NULL OR skipped_count IS NULL')
            ->setParameter('ids', array_column($collection->toArray(), 'id'), $this->getConnection()::PARAM_STR_ARRAY)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchFirstColumn();

        if (empty($ids)) {
            return [];
        }

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('id, state')
            ->addSelect(
                "(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='create' AND entity_name=:entityName AND (import_job_id=import_job.id OR import_job_id IN (SELECT t33.id FROM import_job t33 WHERE t33.deleted=:false AND t33.parent_id=import_job.id))) created_count"
            )
            ->addSelect(
                "(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='update' AND entity_name=:entityName AND (import_job_id=import_job.id OR import_job_id IN (SELECT t33.id FROM import_job t33 WHERE t33.deleted=:false AND t33.parent_id=import_job.id))) updated_count"
            )
            ->addSelect(
                "(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='delete' AND entity_name=:entityName AND (import_job_id=import_job.id OR import_job_id IN (SELECT t33.id FROM import_job t33 WHERE t33.deleted=:false AND t33.parent_id=import_job.id))) deleted_count"
            )
            ->addSelect(
                "(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='error' AND (import_job_id=import_job.id OR import_job_id IN (SELECT t33.id FROM import_job t33 WHERE t33.deleted=:false AND t33.parent_id=import_job.id))) errors_count"
            )
            ->addSelect(
                "(SELECT COUNT(id) FROM import_job_log WHERE deleted=:false AND type='skip' AND entity_name=:entityName AND (import_job_id=import_job.id OR import_job_id IN (SELECT t33.id FROM import_job t33 WHERE t33.deleted=:false AND t33.parent_id=import_job.id))) skipped_count"
            )
            ->from('import_job')
            ->where('id IN (:ids)')
            ->setParameter('entityName', $collection[0]->get('entityName'))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('ids', $ids, $this->getConnection()::PARAM_STR_ARRAY);

        $res = [];
        foreach ($qb->fetchAllAssociative() as $v) {
            $res[$v['id']] = $v;
            if ($v['state'] === 'Success') {
                $this->getConnection()->createQueryBuilder()
                    ->update('import_job')
                    ->set('created_count', ':created_count')
                    ->set('updated_count', ':updated_count')
                    ->set('deleted_count', ':deleted_count')
                    ->set('errors_count', ':errors_count')
                    ->set('skipped_count', ':skipped_count')
                    ->where('id = :id')
                    ->setParameter('created_count', $v['created_count'])
                    ->setParameter('updated_count', $v['updated_count'])
                    ->setParameter('deleted_count', $v['deleted_count'])
                    ->setParameter('errors_count', $v['errors_count'])
                    ->setParameter('skipped_count', $v['skipped_count'])
                    ->setParameter('id', $v['id'])
                    ->executeQuery();
            }
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
