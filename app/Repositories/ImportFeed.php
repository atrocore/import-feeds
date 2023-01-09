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

namespace Import\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Import\Entities\ImportFeed as ImportFeedEntity;

class ImportFeed extends Base
{
    public function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    public function removeInvalidConfiguratorItems(ImportFeedEntity $feed): void
    {
        $feedId = $feed->get('id');

        // delete attribute items
        $this
            ->getPDO()
            ->exec("DELETE FROM import_configurator_item WHERE import_feed_id='$feedId' AND type='Attribute' AND attribute_id NOT IN (SELECT id FROM attribute WHERE deleted=0)");
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $fetchedEntity = $entity->getFeedField('entity');

        $this->setFeedFieldsToDataJson($entity);

        $this->validateFeed($entity);

        if ($entity->get('type') === 'simple') {
            // remove configurator items on Entity change
            if (!$entity->isNew() && $entity->has('entity') && $fetchedEntity !== $entity->get('entity')) {
                $this->getEntityManager()->getRepository('ImportConfiguratorItem')->where(['importFeedId' => $entity->get('id')])->removeCollection();
            }
        }
    }

    public function validateFeed(Entity $entity): void
    {
        $delimiters = [
            $entity->getFeedField('delimiter'),
            $entity->getFeedField('decimalMark'),
//            $entity->getFeedField('thousandSeparator'),
            $entity->getFeedField('fieldDelimiterForRelation')
        ];

        if ($entity->getFeedField('entity') === 'Product') {
            $delimiters[] = $entity->getFeedField('markForNotLinkedAttribute');

            if ($entity->getFeedField('emptyValue') === $entity->getFeedField('markForNotLinkedAttribute') || $entity->getFeedField('nullValue') === $entity->getFeedField('markForNotLinkedAttribute')) {
                throw new BadRequest($this->getLanguage()->translate("nullNoneMarkForNotLinkedAttributeSame", "exceptions", "ImportFeed"));
            }
        }

        if (count(array_unique($delimiters)) !== count($delimiters)) {
            throw new BadRequest($this->getLanguage()->translate('delimitersMustBeDifferent', 'exceptions', 'ImportFeed'));
        }

        if ($entity->getFeedField('emptyValue') === $entity->getFeedField('nullValue')) {
            throw new BadRequest($this->getLanguage()->translate("nullNoneSame", "exceptions", "ImportFeed"));
        }

        if (empty($entity->getFeedField('allColumns'))) {
            throw new BadRequest($this->getLanguage()->translate("allColumnsEmpty", "exceptions", "ImportFeed"));
        }
    }

    protected function setFeedFieldsToDataJson(Entity $entity): void
    {
        $data = !empty($data = $entity->get('data')) ? Json::decode(Json::encode($data), true) : [];

        foreach ($this->getMetadata()->get(['entityDefs', 'ImportFeed', 'fields'], []) as $field => $row) {
            if (empty($row['notStorable']) || empty($row['dataField'])) {
                continue 1;
            }

            if ($entity->has($field)) {
                $data['feedFields'][$field] = $entity->get($field);

                switch ($row['type']) {
                    case 'int':
                        $data['feedFields'][$field] = (int)$data['feedFields'][$field];
                        break;
                    case 'bool':
                        $data['feedFields'][$field] = !empty($data['feedFields'][$field]);
                        break;
                }
            }
        }

        $entity->set('data', $data);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
