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

namespace Import\Listeners;

use Espo\ORM\Entity;
use Espo\Listeners\AbstractListener;
use Espo\Core\EventManager\Event;

class QueueItemEntity extends AbstractListener
{
    public function afterSave(Event $event): void
    {
        $entity = $event->getArgument('entity');
        if (!empty($entity->get('data')->data->importJobId)) {
            $this->updateImportJobState($entity);
        }
    }

    public function afterRemove(Event $event): void
    {
        $entity = $event->getArgument('entity');
        if (!empty($entity->get('data')->data->importJobId)) {
            $this->removeImportJob($entity);
        }
    }

    protected function updateImportJobState(Entity $entity): bool
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $entity->get('data')->data->importJobId);
        if (empty($importJob)) {
            return false;
        }

        if ($importJob->get('state') !== $entity->get('status')) {
            $importJob->set('state', $entity->get('status'));
            $this->getEntityManager()->saveEntity($importJob);
        }

        return true;
    }

    protected function removeImportJob(Entity $entity): bool
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $entity->get('data')->data->importJobId);
        if (empty($importJob)) {
            return false;
        }

        if ($entity->get('status') === 'Pending') {
            $this->getEntityManager()->removeEntity($importJob);
        }

        return true;
    }
}
