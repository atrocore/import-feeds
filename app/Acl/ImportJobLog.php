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

namespace Import\Acl;

use Espo\Core\Acl\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;

class ImportJobLog extends Base
{
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        return $this->getAclManager()->checkScope($user, 'ImportFeed', $action);
    }

    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if (!empty($entity->get('importJobId'))) {
            $importJob = $this->getEntityManager()->getEntity('ImportJob', $entity->get('importJobId'));
        }

        if (empty($importJob)) {
            return false;
        }

        if (in_array($action, ['create', 'delete'])) {
            $action = 'edit';
        }

        return $this->getAclManager()->checkEntity($user, $importJob, $action);
    }
}

