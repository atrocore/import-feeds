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