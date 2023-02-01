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
use Espo\ORM\Entity;

class ImportJobLog extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('entityName') === null) {
            $entity->set('entityName', '');
        }
        if ($entity->get('entityId') === null) {
            $entity->set('entityId', '');
        }
        if ($entity->get('type') === null) {
            $entity->set('type', '');
        }
        if ($entity->get('rowNumber') === null) {
            $entity->set('rowNumber', 0);
        }

        parent::beforeSave($entity, $options);
    }
}
