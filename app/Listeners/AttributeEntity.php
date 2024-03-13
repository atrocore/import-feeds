<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;

class AttributeEntity extends AbstractListener
{
    public function afterRemove(Event $event): void
    {
        $attribute = $event->getArgument('entity');
        $this->getEntityManager()->getRepository('ImportConfiguratorItem')->where(['attributeId' => $attribute->id])->removeCollection();
    }
}