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

use Atro\ORM\DB\RDB\Mapper;
use Espo\ORM\Entity;

class ExtensibleEnum extends Link
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        if (empty($config['importBy'])) {
            $config['importBy'] = ['name'];
        }

        parent::convert($inputRow, $config, $row);
    }

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
        return 'ExtensibleEnumOption';
    }

    protected function prepareWhere(array $config, string $entityName, array &$where): void
    {
        parent::prepareWhere($config, $entityName, $where);

        if (empty($where)) {
            return;
        }

        $where['extensibleEnumId'] = $this->getExtensibleEnumId($config);
    }

    protected function prepareInputForCreateIfNotExist($input, array $config, $row): void
    {
        parent::prepareInputForCreateIfNotExist($input, $config, $row);

        $input->extensibleEnumId = $this->getExtensibleEnumId($config);
    }

    protected function getExtensibleEnumId(array $config): string
    {
        $extensibleEnumId = 'no-such-extensible-enum';
        if (!empty($config['attributeId'])) {
            $attribute = $this->configuratorItem->getAttributeById($config['attributeId']);
            if (!empty($attribute) && !empty($attribute->get('extensibleEnumId'))) {
                $extensibleEnumId = $attribute->get('extensibleEnumId');
            }
        } else {
            $extensibleEnumId = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $config['name'], 'extensibleEnumId']);
        }

        return $extensibleEnumId;
    }

    protected function prepareCollectionBeforeWhereKeysCreated($collection): \Espo\ORM\EntityCollection
    {
        $collectionTmp = clone $collection;
        if(!empty($collection[0]) && $collection[0]->getEntityType() === "ExtensibleEnumOption"){
            foreach ($collection as $key => $option){
                $extensibleEnumsIds = $this->getEntityManager()
                    ->getConnection()
                    ->createQueryBuilder()
                    ->from('extensible_enum_extensible_enum_option')
                    ->select('extensible_enum_id')
                    ->where('extensible_enum_option_id=:extensibleEnumOptionId')
                    ->setParameter('extensibleEnumOptionId', $id = $option->get('id'), Mapper::getParameterType($id))
                    ->fetchAllAssociative();

                $extensibleEnumsIds = array_column($extensibleEnumsIds, 'extensible_enum_id');

                $collectionTmp[$key]->set('extensibleEnumId', array_shift($extensibleEnumsIds));

                foreach ($extensibleEnumsIds as $extensibleEnumId){
                     $cloneOption = clone $option;
                     $cloneOption->set('extensibleEnumId', $extensibleEnumId);
                     $collectionTmp[]= $cloneOption;
                }
            }

        }

        return $collectionTmp;
    }
}
