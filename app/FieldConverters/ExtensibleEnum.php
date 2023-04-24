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

use Espo\ORM\Entity;

class ExtensibleEnum extends Link
{
    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $fieldName = $this->getFieldName($item);

        $restore->$fieldName = $entity->get($item['name']);
    }

    protected function getFieldName(array $config): string
    {
        return $config['name'];
    }

    protected function getForeignEntityName(string $entity, string $field): string
    {
        return 'ExtensibleEnumOption';
    }

    protected function prepareWhere(array $config, string $entityName, array &$where): void
    {
        parent::prepareWhere($config, $entityName, $where);

        $where['extensibleEnumId'] = 'no-such-extensible-enum';

        if (!empty($config['attributeId'])) {
            $attribute = $this->configuratorItem->getAttributeById($config['attributeId']);
            if (!empty($attribute) && !empty($attribute->get('extensibleEnumId'))) {
                $where['extensibleEnumId'] = $attribute->get('extensibleEnumId');
            }
        } else {
            $where['extensibleEnumId'] = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $config['name'], 'extensibleEnumId']);
        }
    }
}
