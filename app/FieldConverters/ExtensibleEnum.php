<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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

        if (empty($where)) {
            return;
        }

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
