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

class TrialImport extends Base
{
    public function run($data, $targetId, $targetType, $scheduledJobId): bool
    {
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId);

        if (empty($scheduledJob)) {
            return true;
        }

        $whereClause = $this->buildWhereClause($scheduledJob);

        $records = $this
            ->getEntityManager()
            ->getRepository('ImportJob')
            ->where($whereClause)
            ->find();

        foreach ($records as $record) {
            $trial = intval($record->get('trial'));
            $record->set('state', 'Pending');
            $record->set('trial', ++$trial);
            $this->getEntityManager()->saveEntity($record);
        }

        return true;
    }

    private function buildWhereClause($scheduledJob): array
    {
        $whereClause = [
            "state"  => 'Failed',
            "trial<" => 3,
        ];

        if (($maximumHoursToLookBack = $scheduledJob->get('maximumHoursToLookBack')) && $maximumHoursToLookBack > 0) {
            $currentDate = new \DateTime();
            $endDate = $currentDate->format('Y-m-d H:i:s');
            $startDate = $currentDate->modify("-{$maximumHoursToLookBack} hours")->format('Y-m-d H:i:s');

            $whereClause['createdAt>='] = $startDate;
            $whereClause['createdAt<='] = $endDate;
        }

        $importFeedIds = array_column(($scheduledJob->get('importFeeds'))->toArray(), 'id');
        if (!empty($importFeedIds)) {
            $whereClause['importFeedId'] = $importFeedIds;
        }

        return $whereClause;
    }
}