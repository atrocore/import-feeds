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

namespace Import\FieldConverters;

use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class LinkMultiple extends Varchar
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        if (!empty($config['column'])) {
            $ids = [];
            foreach ($config['column'] as $column) {
                $items = explode($config['delimiter'], $row[$column]);
                if (empty($items)) {
                    continue 1;
                }
                foreach ($items as $item) {
                    $input = new \stdClass();
                    $this
                        ->getService('ImportConfiguratorItem')
                        ->getFieldConverter('link')
                        ->convert($input, array_merge($config, ['column' => [0], 'default' => null]), [$item]);

                    if (!empty($input)) {
                        $key = $config['name'] . 'Id';
                        $ids[$input->$key] = $input->$key;
                    }
                }
            }
        }

        if (empty($ids) && !empty($config['default'])) {
            $ids = Json::decode($config['default'], true);
        }

        if (!empty($ids)) {
            $inputRow->{$config['name'] . 'Ids'} = array_values($ids);
            $inputRow->{$config['name'] . 'Names'} = array_values($ids);
        }
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $ids = null;
        $names = null;
        $foreigners = $entity->get($item['name'])->toArray();

        if (count($foreigners) > 0) {
            $ids = array_column($foreigners, 'id');
            $names = array_column($foreigners, 'id');
        }

        $restore->{$item['name'] . 'Ids'} = $ids;
        $restore->{$item['name'] . 'Names'} = $names;
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->has('defaultIds')) {
            $entity->set('default', empty($entity->get('defaultIds')) ? null : Json::encode($entity->get('defaultIds')));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $entity->set('defaultIds', null);
        $entity->set('defaultNames', null);
        if (!empty($entity->get('default'))) {
            $relEntityName = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'links', $entity->get('name'), 'entity']);
            if (!empty($relEntityName)) {
                $entity->set('defaultIds', Json::decode($entity->get('default'), true));
                $names = [];
                foreach ($entity->get('defaultIds') as $id) {
                    $relEntity = $this->getEntityManager()->getEntity($relEntityName, $id);
                    $names[$id] = empty($relEntity) ? $id : $relEntity->get('name');
                }
                $entity->set('defaultNames', $names);
            }
        }
    }
}