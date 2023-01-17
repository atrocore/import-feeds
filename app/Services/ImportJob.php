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

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class ImportJob extends Base
{
    protected $mandatorySelectAttributeList = ['message'];
    
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
}
