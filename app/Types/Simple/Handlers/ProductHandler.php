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

namespace Import\Types\Simple\Handlers;

use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\Services\Record;
use Treo\Core\Exceptions\NotModified;

/**
 * Class ProductHandler
 */
class ProductHandler extends AbstractHandler
{
    /**
     * @var array
     */
    protected $images = [];

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var bool
     */
    protected $saved = false;

    /**
     * @param array $fileData
     * @param array $data
     *
     * @return bool
     */
    public function run(array $fileData, array $data): bool
    {
        // prepare entity type
        $entityType = (string)$data['data']['entity'];

        // prepare import result id
        $importResultId = (string)$data['data']['importResultId'];

        // prepare field value delimiter
        $delimiter = $data['data']['delimiter'];

        // create service
        $service = $this->getServiceFactory()->create($entityType);

        // prepare id field
        $idField = isset($data['data']['idField']) ? $data['data']['idField'] : "";

        // find ID row
        $idRow = $this->getIdRow($data['data']['configuration'], $idField);

        // find exists if it needs
        $exists = [];
        if (in_array($data['action'], ['update', 'create_update']) && !empty($idRow)) {
            $exists = $this->getExistsProducts($idRow['name'], array_column($fileData, $idRow['column']), $data['data']['configuration']);
        }

        // prepare file row
        $fileRow = (int)$data['offset'];

        foreach ($fileData as $row) {
            $fileRow++;

            // prepare id
            if ($data['action'] == 'create') {
                $id = null;
            } elseif ($data['action'] == 'update') {
                if (isset($exists[$row[$idRow['column']]])) {
                    $id = $exists[$row[$idRow['column']]];
                } else {
                    // skip row if such item does not exist
                    continue 1;
                }
            } elseif ($data['action'] == 'create_update') {
                $id = (isset($exists[$row[$idRow['column']]])) ? $exists[$row[$idRow['column']]] : null;
            }

            // prepare entity
            $entity = !empty($id) ? $this->getEntityManager()->getEntity($entityType, $id) : null;

            // prepare row
            $input = new \stdClass();
            $restore = new \stdClass();

            try {
                // begin transaction
                $this->getEntityManager()->getPDO()->beginTransaction();

                $additionalFields = [];

                foreach ($data['data']['configuration'] as $item) {
                    if ($item['name'] == 'id') {
                        continue;
                    }

                    if (isset($item['attributeId']) || $item['name'] == 'productCategories') {
                        $additionalFields[] = [
                            'item' => $item,
                            'row'  => $row
                        ];

                        continue;
                    } else {
                        $this->convertItem($input, $entityType, $item, $row, $delimiter);
                    }

                    if (!empty($entity)) {
                        $this->prepareValue($restore, $entity, $item);
                    }
                }

                if (empty($id)) {
                    $entity = $service->createEntity($input);

                    $this->saveRestoreRow('created', $entityType, $entity->get('id'));

                    $this->saved = true;
                } else {
                    $entity = $this->updateEntity($service, (string)$id, $input);

                    if ($entity->isSaved()) {
                        $this->saveRestoreRow('updated', $entityType, [$id => $restore]);
                        $this->saved = true;
                    }
                }

                // prepare product attributes
                $this->attributes = $entity->get('productAttributeValues');

                foreach ($additionalFields as $value) {
                    if (isset($value['item']['attributeId'])) {
                        // import attributes
                        $this->importAttribute($entity, $value, $delimiter);
                    }
                }

                if (!is_null($entity) && $this->saved) {
                    // prepare action
                    $action = empty($id) ? 'create' : 'update';

                    // push log
                    $this->log($entityType, $importResultId, $action, (string)$fileRow, (string)$entity->get('id'));
                }

                $this->saved = false;

                $this->getEntityManager()->getPDO()->commit();
            } catch (\Throwable $e) {
                // roll back transaction
                $this->getEntityManager()->getPDO()->rollBack();

                // push log
                $this->log($entityType, $importResultId, 'error', (string)$fileRow, $e->getMessage());
            }
        }

        return true;
    }

    /**
     * @param Record    $service
     * @param string    $id
     * @param \stdClass $data
     */
    protected function updateEntity(Record $service, string $id, \stdClass $data): ?Entity
    {
        try {
            $result = $service->updateEntity($id, $data);
        } catch (NotModified $e) {
            $result = $service->readEntity($id);
        }

        return $result;
    }

    /**
     * @param Entity $product
     * @param array  $data
     * @param string $delimiter
     */
    protected function importAttribute(Entity $product, array $data, string $delimiter)
    {
        $attribute = null;
        $entityType = 'ProductAttributeValue';
        $service = $this->getServiceFactory()->create($entityType);

        $inputRow = new \stdClass();
        $restoreRow = new \stdClass();

        $conf = $data['item'];
        $conf['name'] = 'value';
        // check for multiLang
        if (isset($conf['locale']) && !is_null($conf['locale'])) {
            if ($this->getConfig()->get('isMultilangActive')) {
                $conf['name'] .= Util::toCamelCase(strtolower($conf['locale']), '_', true);
            }
        }
        $row = $data['row'];

        foreach ($this->attributes as $item) {
            if ($item->get('attributeId') == $conf['attributeId'] && $item->get('scope') == $conf['scope']) {
                if ($conf['scope'] == 'Global'
                    || ($conf['scope'] == 'Channel' && $conf['channelId'] == $item->get('channelId'))) {
                    $inputRow->id = $item->get('id');
                    $this->prepareValue($restoreRow, $item, $conf);
                }
            }
        }

        // prepare attribute
        if (!isset($this->attributes[$conf['attributeId']])) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $conf['attributeId']);
            $this->attributes[$conf['attributeId']] = $attribute;
        } else {
            $attribute = $this->attributes[$conf['attributeId']];
        }
        $conf['attribute'] = $attribute;

        // convert attribute value
        $this->convertItem($inputRow, $entityType, $conf, $row, $delimiter);

        if (!isset($inputRow->id)) {
            $inputRow->productId = $product->get('id');
            $inputRow->attributeId = $conf['attributeId'];
            $inputRow->scope = $conf['scope'];

            if ($conf['scope'] == 'Channel') {
                $inputRow->channelId = $conf['channelId'];
                $inputRow->channelName = $conf['channelName'];
            }

            $entity = $service->createEntity($inputRow);
            $this->attributes[] = $entity;

            $this->saveRestoreRow('created', $entityType, $entity->get('id'));

            $this->saved = true;
        } else {
            $id = $inputRow->id;
            unset($inputRow->id);

            $entity = $this->updateEntity($service, $id, $inputRow);

            if ($entity->isSaved()) {
                $this->saveRestoreRow('updated', $entityType, [$id => $restoreRow]);
                $this->saved = true;
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function getType(string $entityType, array $item): ?string
    {
        $result = null;

        if (isset($item['attributeId']) && isset($item['type'])) {
            $result = $item['type'];
        } else {
            $result = parent::getType($entityType, $item);
        }
        return $result;
    }

    /**
     * @param string $name
     * @param array  $ids
     * @param array  $configuration
     *
     * @return array
     */
    protected function getExistsProducts(string $name, array $ids, array $configuration): array
    {
        $catalogId = 'no-such-catalog';
        foreach ($configuration as $row) {
            if ($row['name'] === 'catalog') {
                if (empty($row['column'])) {
                    $catalogId = $row['default'];
                } else {
                    $catalog = $this->getEntityManager()->getRepository('Catalog')->where([$row['field'] => $row['column']])->findOne();
                    if (!empty($catalog)) {
                        $catalogId = $catalog->get('id');
                    }
                }
                break;
            }
        }

        $select = ($name == 'id') ? [$name] : ['id', $name];

        // get data
        $data = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select($select)
            ->where([$name => $ids, 'catalogId' => $catalogId])
            ->find();

        $result = [];

        if (count($data) > 0) {
            foreach ($data as $entity) {
                $result[$entity->get($name)] = $entity->get('id');
            }
        }

        return $result;
    }
}