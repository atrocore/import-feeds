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

use Atro\Core\Templates\Repositories\Base;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ImportJobLog extends Base
{
    protected bool $cacheable = false;

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('entityName') === null) {
            $entity->set('entityName', '');
        }
        if ($entity->get('entityId') === null) {
            $entity->set('entityId', '');
        }
        if ($entity->get('type') === null) {
            $entity->set('type', '');
        }
        if ($entity->get('rowNumber') === null) {
            $entity->set('rowNumber', 0);
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->createParentJobLog($entity, $options);
    }

    public function prepareMessage(Entity $entity): void
    {
        if ($entity->get('type') !== 'error' || $entity->get('message') !== null) {
            return;
        }

        $res = $this->getConnection()->createQueryBuilder()
            ->select('t.message')
            ->from('import_job_log', 't')
            ->where('t.deleted=:false')
            ->andWhere('t.type=:errorType')
            ->andWhere('t.row_number=:rowNumber')
            ->andWhere('t.import_job_id IN (SELECT id FROM import_job WHERE deleted=:false AND parent_id=:id)')
            ->orderBy('t.created_at', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $entity->get('importJobId'))
            ->setParameter('rowNumber', $entity->get('rowNumber'), ParameterType::INTEGER)
            ->setParameter('errorType', 'error')
            ->fetchFirstColumn();

        $entity->set('message', implode(' | ', $res));

        $importJob = $this->getEntityManager()->getRepository('ImportJob')->get($entity->get('importJobId'));
        if (in_array($importJob->get('state'), ['Failed', 'Canceled', 'Success'])) {
            $this->getConnection()->createQueryBuilder()
                ->update('import_job_log')
                ->set('message', ':message')
                ->where('id=:id')
                ->setParameter('id', $entity->get('id'))
                ->setParameter('message', $entity->get('message'))
                ->executeQuery();
        }
    }

    public function createParentJobLog(Entity $entity, array $options): void
    {
        if (!empty($options['skipParentLog'])) {
            return;
        }

        $importJob = $this->getEntityManager()->getRepository('ImportJob')->get($entity->get('importJobId'));
        if (empty($importJob->get('parentId'))) {
            return;
        }

        $parentJob = $this->getEntityManager()->getRepository('ImportJob')->get($importJob->get('parentId'));
        if (empty($parentJob)) {
            return;
        }

        $input = $this->getMemoryStorage()->get("import_job_{$importJob->get('id')}_input");

        $rowNumberPart = $this->getMemoryStorage()->get("import_job_{$importJob->get('id')}_rowNumberPart") ?? 0;

        $rowNumber = $rowNumberPart + $entity->get('rowNumber');

        if ($parentJob->get('entityName') === $entity->get('entityName')) {
            $parentLog = $this->getEntityManager()->getEntity('ImportJobLog');
            $parentLog->set('name', $entity->get('name'));
            $parentLog->set('entityName', $entity->get('entityName'));
            $parentLog->set('entityId', $entity->get('entityId'));
            $parentLog->set('importJobId', $importJob->get('parentId'));
            $parentLog->set('type', $entity->get('type'));
            $parentLog->set('rowNumber', $rowNumber);
            $parentLog->set('message', $entity->get('message'));
            try {
                $this->getEntityManager()->saveEntity($parentLog, ['skipParentLog' => true]);
            } catch (\Throwable $e) {
                // ignore
            }
        } else {
            if ($entity->get('entityName') === 'ProductAttributeValue') {
                $type = $entity->get('type');
                switch ($type) {
                    case 'create':
                    case 'delete':
                        $type = 'update';
                        break;
                    case 'skip':
                        return;
                }

                if (!property_exists($input, 'productId')) {
                    return;
                }

                $parentLog = $this->getEntityManager()->getEntity('ImportJobLog');
                $parentLog->set('name', $entity->get('name'));
                $parentLog->set('entityName', 'Product');
                $parentLog->set('entityId', $input->productId);
                $parentLog->set('importJobId', $importJob->get('parentId'));
                $parentLog->set('type', $type);
                $parentLog->set('rowNumber', $rowNumber);
                $parentLog->set('message', null);
                try {
                    $this->getEntityManager()->saveEntity($parentLog, ['skipParentLog' => true]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }
}
