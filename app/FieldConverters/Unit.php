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

namespace Import\FieldConverters;

use Espo\ORM\Entity;

class Unit extends Link
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

    protected function getForeignEntityName(array $config): string
    {
        return 'Unit';
    }

    protected function prepareWhere(array $config, string $entityName, array &$where): void
    {
        parent::prepareWhere($config, $entityName, $where);

        $where['measureId'] = 'no-such-measure';

        if (!empty($config['attributeId'])) {
            $attribute = $this->configuratorItem->getAttributeById($config['attributeId']);
            if (!empty($attribute) && !empty($attribute->get('measureId'))) {
                $where['measureId'] = $attribute->get('measureId');
            }
        } else {
            $where['measureId'] = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $config['name'], 'measureId']);
        }
    }
}