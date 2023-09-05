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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class LinkMultiple extends Varchar
{
    /**
     * @var string
     */
    protected string $relationEntityName;

    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $ids = [];
        $fieldName = $this->getFieldName($config);

        foreach ($config['column'] as $column) {
            if ($row[$column] === $config['markForNoRelation']) {
                $inputRow->$fieldName = [];
                return;
            }
        }


        $this->relationEntityName = $config['relEntityName'] ?? $this->getForeignEntityName($config['entity'], $config['name']);

        $searchData = $this->prepareItem($config, $config['column'], $row);
        $insertData = $this->prepareItem($config, empty($config['foreignColumn']) ? $config['column'] : $config['foreignColumn'], $row);

        foreach ($searchData as $key => $item) {
            $foreignColumn = [];

            if (isset($insertData[$key])) {
                $k = count($item);
                foreach ($insertData[$key] as $data) {
                    $foreignColumn[$k] = $data;
                    $k++;
                }
            }

            $id = $this->convertItem($config, ['column' => array_keys($item), 'foreignColumn' => array_keys($foreignColumn)], array_merge($item, $foreignColumn));
            if ($id !== null) {
                $ids[$id] = $id;
            }

        }

        if (empty($ids) && !empty($config['default'])) {
            $ids = Json::decode($config['default'], true);
        }

        $ids = array_values($ids);


        if (!empty($inputRow->$fieldName)) {
            $inputRow->$fieldName = array_merge($inputRow->$fieldName, $ids);
        } else {
            $inputRow->$fieldName = $ids;
        }

        if ($config['type'] === 'Attribute') {
            $inputRow->{$config['name']} = $inputRow->$fieldName;
        }

        if (empty($config['replaceArray'])) {
            if (property_exists($inputRow, $fieldName) && empty($inputRow->$fieldName)) {
                unset($inputRow->$fieldName);
            } elseif (property_exists($inputRow, $config['name']) && empty($inputRow->{$config['name']})) {
                unset($inputRow->{$config['name']});
            } else {
                $inputRow->{$config['name'] . 'AddOnlyMode'} = 1;
            }
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
            $relEntityName = $this->getForeignEntityName($entity->get('entity'), $entity->get('name'));
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

    /**
     * @param array $config
     * @param array $column
     * @param array $row
     *
     * @return string|null
     */
    protected function convertItem(array $config, array $column, array $row): ?string
    {
        $input = new \stdClass();
        $this
            ->getService('ImportConfiguratorItem')
            ->getFieldConverter('link')
            ->convert($input, array_merge($config, $column, ['default' => null]), $row);

        $key = $config['name'] . 'Id';
        if (property_exists($input, $key) && $input->$key !== null) {
            return $input->$key;
        }

        return null;
    }

    /**
     * @param array $config
     * @param array $columns
     * @param array $row
     *
     * @return array
     *
     * @throws BadRequest
     */
    protected function prepareItem(array $config, array $columns, array $row): array
    {
        $result = [];

        if (count($columns) == 1) {
            $value = $row[$columns[0]];

            if (strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $value = null;
            }

            if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }

            if ($value !== null) {
                $values = explode($config['delimiter'], (string)$value);

                foreach ($values as $value) {
                    $result[] = [$value];
                }
            }
        } else {
            $rows = [];

            foreach ($columns as $column) {
                $value = explode($config['delimiter'], (string)$row[$column]);

                if (count($value) > 1) {
                    throw new BadRequest(sprintf($this->translate('listSeparatorNotAllowed', 'exceptions', 'ImportFeed'), $this->relationEntityName));
                }

                $rows[] = $value[0];
            }

            $result[] = $rows;
        }

        return $result;
    }

    protected function getFieldName(array $config): string
    {
        return $config['name'] . 'Ids';
    }

    protected function getForeignEntityName(string $entity, string $field): string
    {
        return $this->getMetadata()->get(['entityDefs', $entity, 'links', $field, 'entity']);
    }
}
