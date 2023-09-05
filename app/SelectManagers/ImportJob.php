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

namespace Import\SelectManagers;

use Espo\Core\SelectManagers\Base;

class ImportJob extends Base
{
    protected function access(&$result)
    {
        $importFeeds = $this->getEntityManager()->getRepository('ImportFeed')
            ->select(['id'])
            ->find($this->createSelectManager('ImportFeed')->getSelectParams([], true, true));

        $result['whereClause'][] = ['OR' => ['importFeedId' => array_column($importFeeds->toArray(), 'id')]];
    }

    protected function boolFilterOnlyImportFailed24Hours(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedImportJobFilteredIds(1)
        ];
    }

    protected function boolFilterOnlyImportFailed7Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedImportJobFilteredIds(7)
        ];
    }

    protected function boolFilterOnlyImportFailed28Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedImportJobFilteredIds(28)
        ];
    }

    protected function getFailedImportJobFilteredIds(int $interval): array
    {
        $query = "SELECT id
            FROM `import_job`
            WHERE state = 'Failed'
            AND start >= DATE_SUB(NOW(), INTERVAL $interval DAY)";

        return array_column(
            $this->getEntityManager()->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC),
            'id'
        );
    }
}
