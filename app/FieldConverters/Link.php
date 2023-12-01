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

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Link extends Varchar
{
    const ALLOWED_TYPES = ['bool', 'enum', 'varchar', 'float', 'int', 'text', 'wysiwyg'];

    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? null : $config['default'];

        if (isset($config['column'])) {
            if (count($config['column']) === 1) {
                $value = $this->getSearchValue($config['column'][0], $config, $row);
            } else {
                $value = [];

                foreach ($config['column'] as $key => $column) {
                    $value[] = $this->getSearchValue($column, $config, $row);
                }
            }

            if ($value !== null && $value !== (string)$config['emptyValue'] && $value !== (string)$config['markForNoRelation']) {
                if (isset($config['relEntityName'])) {
                    $entityName = $config['relEntityName'];
                } else {
                    $entityName = $this->getForeignEntityName($config);
                }

                $input = new \stdClass();

                $where = [];

                $values = !is_array($value) ? explode((string)$config['fieldDelimiterForRelation'], (string)$value) : $value;
                foreach ($config['importBy'] as $k => $field) {
                    $fieldData = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $field], ['type' => 'varchar']);

                    if (empty($fieldData['type']) || !in_array($fieldData['type'], self::ALLOWED_TYPES)) {
                        continue 1;
                    }

                    if (!array_key_exists($k, $values)) {
                        throw new BadRequest(sprintf($this->translate('wrongImportByValuesCount', 'exceptions', 'ImportFeed'), $entityName));
                    }

                    $this
                        ->getService('ImportConfiguratorItem')
                        ->getFieldConverter($fieldData['type'])
                        ->convert($input, ['name' => $field, 'column' => [0], 'default' => null], [$values[$k]]);

                    if (empty($fieldData['notStorable']) && isset($values[$k]) && $values[$k] !== '' && $values[$k] !== (string)$config['emptyValue']) {
                        $where[$field] = $values[$k];
                    }
                }

                $this->prepareWhere($config, $entityName, $where);

                $entity = null;
                if (!empty($where)) {
                    $entity = $this
                        ->getEntityManager()
                        ->getRepository($entityName)
                        ->where($where)
                        ->findOne();

                    if (empty($entity) && empty($config['createIfNotExist'])) {
                        throw new BadRequest(
                            sprintf($this->translate('noRecordsFoundFor', 'exceptions', 'ImportFeed'), $this->translate($entityName, 'scopeNames'), json_encode($where))
                        );
                    }
                }

                if (empty($entity) && $input !== new \stdClass() && !empty($config['createIfNotExist'])) {
                    $user = $this->container->get('user');
                    $userId = empty($user) ? null : $user->get('id');

                    $input->ownerUserId = $userId;
                    $input->ownerUserName = $userId;
                    $input->assignedUserId = $userId;
                    $input->assignedUserName = $userId;

                    if (!empty($config['foreignImportBy']) && !empty($config['foreignColumn'])) {
                        $this->prepareInputForCreateIfNotExist($input, $config, $row);
                    }

                    try {
                        $entity = $this->getService($entityName)->createEntity($input);
                    } catch (\Throwable $e) {
                        $className = get_class($e);

                        $message = sprintf($this->translate('relatedEntityCreatingFailed', 'exceptions', 'ImportFeed'), $this->translate($entityName, 'scopeNames'));
                        $message .= ' ' . $e->getMessage();

                        throw new $className($message);
                    }

                    // for attribute
                    if (isset($config['attributeId']) && !empty($config['relEntityName']) && !empty($entity)) {
                        $entity = $entity->get('file');
                    }
                }

                if (!empty($entity)) {
                    $value = $entity->get('id');
                } else {
                    $value = $default;
                }
            }
        } else {
            $value = $default;
        }

        if ($value === null) {
            return;
        }

        if ($value === '' || $value === (string)$config['emptyValue']) {
            $value = $default;
        }

        if ($value === (string)$config['markForNoRelation']) {
            $value = null;
        }

        $fieldName = $this->getFieldName($config);

        $inputRow->$fieldName = $value;

        if ($config['entity'] === 'ProductAttributeValue' && !empty($entity) && $entity->getEntityType() === 'Attribute') {
            $inputRow->attributeType = $entity->get('type');
        }
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $value = null;

        if (!empty($foreign = $entity->get($item['name']))) {
            $value = $foreign->get('id');
        }

        $fieldName = $this->getFieldName($item);

        $restore->$fieldName = $value;
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $rows): void
    {
        $entityName = $this->getForeignEntityName($configuration);

        if (!empty($config['createIfNotExist'])) {
            throw new BadRequest("Wrong configuration. System cannot create Identifier. $entityName entity must already exist.");
        }

        $ids = !empty($configuration['default']) ? [$configuration['default']] : [];
        if (!empty($configuration['importBy']) && !empty($configuration['column'])) {
            $res = [];
            foreach ($configuration['importBy'] as $k => $field) {
                $res[$field] = array_column($rows, $configuration['column'][$k]);
            }
            $entityName = $this->getForeignEntityName($configuration);
            $collection = $this->getEntityManager()->getRepository($entityName)->select(['id'])->where($res)->find();
            $ids = array_column($collection->toArray(), 'id');
        }

        if (empty($ids)) {
            throw new BadRequest("Wrong configuration. No $entityName records found.");
        }

        $fieldName = $this->getFieldName($configuration);
        $where[$fieldName] = $ids;
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->has('defaultId')) {
            $entity->set('default', empty($entity->get('defaultId')) ? null : $entity->get('defaultId'));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $entity->set('defaultId', null);
        $entity->set('defaultName', null);
        if (!empty($entity->get('default'))) {
            $relEntityName = $this->getForeignEntityName(['entity' => $entity->get('entity'), 'name' => $entity->get('name')]);
            if (!empty($relEntityName)) {
                $entity->set('defaultId', $entity->get('default'));
                $relEntity = $this->getEntityManager()->getEntity($relEntityName, $entity->get('defaultId'));
                $entity->set('defaultName', empty($relEntity) ? $entity->get('defaultId') : $relEntity->get('name'));
            }
        }
    }

    protected function getSearchValue($column, array $config, array $row)
    {
        $value = $row[$column] ?? null;
        $this->deletePAV($value, $config);
        if (strtolower((string)$value) === strtolower((string)$config['emptyValue'])) {
            $value = (string)$config['emptyValue'];
        }
        if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
            $value = null;
        }

        return $value;
    }

    protected function getForeignEntityName(array $config): string
    {
        if (isset($config['attributeId'])) {
            return $this->getEntityById('Attribute', $config['attributeId'])->get('entityType');
        }

        $res = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $config['name'], 'entity']);
        if (empty($res)) {
            $res = $this->getMetadata()->get(['entityDefs', $config['entity'], 'links', $config['name'], 'entity']);
        }

        return $res;
    }

    protected function getFieldName(array $config): string
    {
        return $config['name'] . 'Id';
    }

    protected function prepareWhere(array $config, string $entityName, array &$where): void
    {
        if ($entityName === 'Asset' && in_array('url', $config['importBy'])) {
            $where = [];
        }
    }

    protected function prepareInputForCreateIfNotExist($input, array $config, array $row): void
    {
        $foreignValues = [];
        $foreignColumn = $config['foreignColumn'];
        $foreignImportBy = $config['foreignImportBy'];

        if (count($foreignColumn) === 1) {
            $foreignValues = explode($config['fieldDelimiterForRelation'], (string)$row[$foreignColumn[0]]);
        } else {
            foreach ($foreignColumn as $column) {
                $foreignValues[] = $row[$column];
            }
        }

        foreach ($foreignImportBy as $key => $field) {
            if (isset($foreignValues[$key])) {
                $input->{$field} = $foreignValues[$key];
            }
        }
    }
}
