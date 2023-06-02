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

namespace Import\FieldConverters;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

/**
 * Class Currency
 */
class Unit extends Varchar
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $unit = $row[$config['column'][0]];
        if (empty($unit) && !empty($config['default'])) {
            $unit = (string)$config['default'];
        }

        if (is_string($unit)) {
            $inputRow->{$config['name']} = trim($unit);
        }
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
            $entity->set('defaultId', $entity->get('default'));
            $relEntity = $this->getEntityManager()->getEntity('Unit', $entity->get('defaultId'));
            $entity->set('defaultName', empty($relEntity) ? $entity->get('defaultId') : $relEntity->get('name'));
        }
    }
}