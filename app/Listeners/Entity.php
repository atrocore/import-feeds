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

namespace Import\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Listeners\AbstractListener;

class Entity extends AbstractListener
{
    public function beforeGetSelectParams(Event $event): void
    {
        $entityType = $event->getArgument('entityType');
        $params = $event->getArgument('params');

        if (!empty($params['where'])) {
            foreach ($params['where'] as $k => $item) {
                if (!empty($newItem = $this->prepareImportJobFilter($entityType, $item))) {
                    $params['where'][$k] = $newItem;
                }
            }
            $event->setArgument('params', $params);
        }
    }

    protected function prepareImportJobFilter(string $scope, array $item): array
    {
        if (
            isset($item['attribute'])
            && in_array($item['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            return [
                'type'      => 'in',
                'attribute' => 'id',
                'value'     => $this->getEntitiesIds([
                    'entityName'  => $scope,
                    'type'        => [$this->getJobType($item['attribute'])],
                    'importJobId' => (array)$item['value']
                ])
            ];
        }

        if (
            !empty($item['value'][1]['type'])
            && $item['value'][1]['type'] === 'notIn'
            && in_array($item['value'][1]['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            return [
                'type'      => 'notIn',
                'attribute' => 'id',
                'value'     => $this->getEntitiesIds([
                    'entityName'  => $scope,
                    'type'        => [$this->getJobType($item['value'][1]['attribute'])],
                    'importJobId' => (array)$item['value'][1]['value']
                ])
            ];
        }

        if (
            !empty($item['value'][1]['type'])
            && $item['value'][1]['type'] === 'equals'
            && in_array($item['value'][1]['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            return [
                'type'      => 'notIn',
                'attribute' => 'id',
                'value'     => $this->getEntitiesIds([
                    'entityName' => $scope,
                    'type'       => [$this->getJobType($item['value'][1]['attribute'])]
                ])
            ];
        }

        if (
            !empty($item['value'][1]['type'])
            && $item['value'][1]['type'] === 'notEquals'
            && in_array($item['value'][1]['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            return [
                'type'      => 'in',
                'attribute' => 'id',
                'value'     => $this->getEntitiesIds([
                    'entityName' => $scope,
                    'type'       => [$this->getJobType($item['value'][1]['attribute'])]
                ])
            ];
        }

        return [];
    }

    protected function getEntitiesIds(array $where): array
    {
        return array_column($this->getEntityManager()->getRepository('ImportJobLog')->select(['entityId'])->where($where)->find()->toArray(), 'entityId');
    }

    protected function getJobType(string $name): string
    {
        return $name === 'filterCreateImportJob' ? 'create' : 'update';
    }
}
