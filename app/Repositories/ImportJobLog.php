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

namespace Import\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ImportJobLog extends Base
{
    protected bool $cacheable = false;

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
