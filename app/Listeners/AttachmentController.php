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

use Espo\Core\Exceptions\BadRequest;
use Espo\Listeners\AbstractListener;
use Espo\Core\EventManager\Event;

class AttachmentController extends AbstractListener
{
    public function afterActionCreate(Event $event): void
    {
        $data = $event->getArgument('data');
        $result = $event->getArgument('result');

        $this->validateImportAttachment($data, $result);
    }

    public function afterActionCreateChunks(Event $event): void
    {
        $data = $event->getArgument('data');
        $result = $event->getArgument('result');

        if (!empty($result['attachment'])) {
            $this->validateImportAttachment($data, json_decode(json_encode($result['attachment'])));
        }
    }

    protected function validateImportAttachment($inputData, $attachment): void
    {
        if (empty($attachment) || !is_object($attachment)) {
            return;
        }

        if (!property_exists($attachment, 'relatedType') || $attachment->relatedType !== 'ImportFeed') {
            return;
        }

        if (!property_exists($attachment, 'field') || !in_array($attachment->field, ['importFile', 'file'])) {
            return;
        }

        if (property_exists($inputData, 'modelAttributes') && property_exists($inputData->modelAttributes, 'format')) {
            $method = "validate{$inputData->modelAttributes->format}File";
            $service = $this->getService('ImportFeed');
            if (method_exists($service, $method)) {
                try {
                    $service->$method($attachment->id);
                } catch (BadRequest $e) {
                    if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $attachment->id))) {
                        $this->getEntityManager()->removeEntity($attachment);
                    }
                    throw $e;
                }
            }
        }
    }
}
