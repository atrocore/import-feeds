<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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
