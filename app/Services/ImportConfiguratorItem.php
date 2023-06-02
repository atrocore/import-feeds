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

namespace Import\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Import\FieldConverters\Varchar;

class ImportConfiguratorItem extends Base
{
    protected $mandatorySelectAttributeList
        = [
            'importFeedId',
            'importBy',
            'createIfNotExist',
            'replaceArray',
            'default',
            'type',
            'attributeId',
            'scope',
            'locale',
            'sortOrder',
            'foreignColumn',
            'foreignImportBy',
            'attributeValue'
        ];

    protected array $attributes = [];

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $entity) {
            if (!empty($entity->get('importFeedId'))) {
                $importFeedsIds[] = $entity->get('importFeedId');
            }

            if ($entity->get('type') === 'Attribute' && !empty($entity->get('attributeId'))) {
                $attributesIds[] = $entity->get('attributeId');
            }
        }

        if (empty($importFeedsIds)) {
            return;
        }

        $importFeeds = $this
            ->getEntityManager()
            ->getRepository('ImportFeed')
            ->where(['id' => $importFeedsIds])
            ->find();

        if (!empty($attributesIds)) {
            $attributes = $this
                ->getEntityManager()
                ->getRepository('Attribute')
                ->where(['id' => $attributesIds])
                ->find();
        }

        foreach ($collection as $entity) {
            foreach ($importFeeds as $importFeed) {
                if ($importFeed->get('id') === $entity->get('importFeedId')) {
                    $entity->set('entity', $importFeed->getFeedField('entity'));
                    $entity->set('sourceFields', $importFeed->get('sourceFields'));
                    break 1;
                }
            }

            $fieldType = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $entity->get('name'), 'type'], 'varchar');
            if ($entity->get('type') === 'Attribute' && !empty($attributesIds)) {
                foreach ($attributes as $attribute) {
                    if ($attribute->get('id') === $entity->get('attributeId')) {
                        $entity->set('name', $attribute->get('name'));
                        $entity->set('attributeCode', $attribute->get('code'));
                        $entity->set('attributeType', $attribute->get('type'));
                        $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
                        $fieldType = $attribute->get('type');
                        break 1;
                    }
                }
            }

            $this->prepareDefaultField($fieldType, $entity);
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($entity->has('entity')) {
            return;
        }

        if (empty($importFeed = $entity->get('importFeed'))) {
            return;
        }

        $entity->set('entity', $importFeed->getFeedField('entity'));
        $entity->set('sourceFields', $importFeed->get('sourceFields'));

        if ($entity->get('type') === 'Attribute') {
            if (empty($attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId')))) {
                throw new BadRequest('No such Attribute.');
            }
            $entity->set('name', $attribute->get('name'));
            $entity->set('attributeCode', $attribute->get('code'));
            $entity->set('attributeType', $attribute->get('type'));
            $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
            $fieldType = $attribute->get('type');
        } else {
            $fieldType = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $entity->get('name'), 'type'], 'varchar');
        }

        $this->prepareDefaultField($fieldType, $entity);
    }

    public function getFieldConverter($type)
    {
        $class = $this->getMetadata()->get(['import', 'configurator', 'fields', $type, 'converter'], Varchar::class);

        return new $class($this->getInjection('container'), $this);
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_previousItemId') && property_exists($data, '_itemId')) {
            $this->getRepository()->updatePosition((string)$data->_itemId, (string)$data->_previousItemId);
            return $this->readEntity($id);
        }

        return parent::updateEntity($id, $data);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function prepareDefaultField(string $type, Entity $entity): void
    {
        if (!empty($converter = $this->getFieldConverter($type))) {
            $converter->prepareForOutputConfiguratorDefaultField($entity);
        }
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    public function prepareDuplicateEntityForSave(Entity $entity, Entity $newImportConfiguratorEntity): void
    {
        $fieldType = $this->getFieldType($entity, $newImportConfiguratorEntity);
        $this->prepareDefaultField($fieldType, $newImportConfiguratorEntity);
    }

    public function getFieldType(Entity $entity, Entity $importConfiguratorEntity): string
    {
        if ($importConfiguratorEntity->get('type') === 'Attribute') {
            if (empty($attribute = $this->getEntityManager()->getEntity('Attribute', $importConfiguratorEntity->get('attributeId')))) {
                throw new BadRequest('No such Attribute.');
            }
            $fieldType = $attribute->get('type');
        } else {
            $fieldType = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $importConfiguratorEntity->get('name'), 'type'], 'varchar');
        }

        return $fieldType;
    }

    public function getAttributeById(string $id): ?Entity
    {
        if (!isset($this->attributes[$id])) {
            $this->attributes[$id] = $this->getEntityManager()->getEntity('Attribute', $id);
        }

        return $this->attributes[$id];
    }
}
