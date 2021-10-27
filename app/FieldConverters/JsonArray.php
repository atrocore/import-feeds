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

class JsonArray extends Varchar
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            if ($value === $config['emptyValue']) {
                $value = '';
            }
            if ($value === $config['nullValue']) {
                $value = null;
            }
        } else {
            $value = $config['default'];
        }

        if (is_string($value)) {
            $value = explode($config['delimiter'], $value);
        }

        if ($value !== null) {
            $value = Json::encode($value);
        }

        $inputRow->{$config['name']} = $value;
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->isAttributeChanged('default')) {
            $entity->set('default', Json::encode($entity->get('default')));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $entity->set('default', !empty($entity->get('default')) ? Json::decode($entity->get('default'), true) : []);
    }
}
