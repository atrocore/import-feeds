<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\Jobs;

use Espo\Core\Jobs\Base;

class ImportFeed extends Base
{
    public function run($data, $targetId, $targetType, $scheduledJobId): bool
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId);
        if (empty($scheduledJob) || empty($importFeedId = $scheduledJob->get('importFeedId'))) {
            return true;
        }

        return $this->getServiceFactory()->create('ImportFeed')->runImport($importFeedId, '');
    }
}
