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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Import\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class ImportConfiguratorItem extends Base
{
    public function updateSortOrder(array $ids): void
    {
        foreach ($ids as $k => $id) {
            $this
                ->getConnection()
                ->createQueryBuilder()
                ->update('import_configurator_item')
                ->set('sort_order', ':sortOrder')->setParameter('sortOrder', $k)
                ->where('id=:id')->setParameter('id', $id)
                ->executeQuery();
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($importFeed = $entity->get('importFeed'))) {
            throw new BadRequest('ImportFeed is required for Configurator item.');
        }

        if ($entity->get('type') === 'Field') {
            $type = $this->getMetadata()->get(['entityDefs', $importFeed->getFeedField('entity'), 'fields', $entity->get('name'), 'type'], 'varchar');
        } elseif ($entity->get('type') === 'Attribute') {
            if (empty($attribute = $entity->get('attribute'))) {
                throw new BadRequest('No such Attribute.');
            }
            $type = $attribute->get('type');
        }

        $this->prepareDefaultField($type, $entity);

        if (in_array($type, ['asset', 'link', 'linkMultiple']) && empty($entity->get('importBy')) && empty($entity->get('default')) && $entity->get('default') !== false) {
            throw new BadRequest($this->getInjection('language')->translate('importByIsRequired', 'exceptions', 'ImportConfiguratorItem'));
        }

        if (empty($entity->get('column')) && empty($entity->get('default')) && $entity->get('default') !== false) {
            throw new BadRequest($this->getInjection('language')->translate('columnOrDefaultValueIsRequired', 'exceptions', 'ImportConfiguratorItem'));
        }

        if (!empty($entity->get('createIfNotExist'))) {
            $columns = $entity->get('foreignColumn');
            $importBy = $entity->get('foreignImportBy');

            if (empty($columns) || empty($importBy)) {
                throw new BadRequest($this->getInjection('language')->translate('foreignColumnsAndFieldsEmpty', 'exceptions', 'ImportConfiguratorItem'));
            }

            if ((count($columns) === 1 && count($importBy) < 1) || (count($columns) > 1 && count($columns) !== count($importBy))) {
                throw new BadRequest($this->getInjection('language')->translate('wrongFieldsNumber', 'exceptions', 'ImportConfiguratorItem'));
            }
        }

        $last = $this
            ->where(['importFeedId' => $entity->get('importFeedId')])
            ->order('sortOrder', 'DESC')
            ->findOne();

        $entity->set('sortOrder', empty($last) ? 0 : (int)$last->get('sortOrder') + 1);

        parent::beforeSave($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
    }

    protected function prepareDefaultField(string $type, Entity $entity): void
    {
        $this
            ->getInjection('container')
            ->get('serviceFactory')
            ->create('ImportConfiguratorItem')
            ->getFieldConverter($type)
            ->prepareForSaveConfiguratorDefaultField($entity);
    }
}
