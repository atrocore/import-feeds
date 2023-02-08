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

use Espo\ORM\Entity;
use Espo\Services\QueueManagerBase;

class BackgroundFileParser extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        if (empty($data['payload'])) {
            return false;
        }

        $sourceFields = $this->getContainer()->get('serviceFactory')->create('ImportFeed')->getFileColumns(json_decode(json_encode($data['payload'])));

        $this->qmItem->get('data')->sourceFields = $sourceFields;
        $this->getEntityManager()->saveEntity($this->qmItem, ['skipAll' => true]);

        return true;
    }

    public function getNotificationMessage(Entity $queueItem): string
    {
        return '';
    }
}
