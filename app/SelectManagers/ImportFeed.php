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

namespace Import\SelectManagers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;

class ImportFeed extends \Espo\Core\SelectManagers\Base
{
    protected function boolFilterOnlyImportFailed24Hours(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(1)
        ];
    }

    protected function boolFilterOnlyImportFailed7Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(7)
        ];
    }

    protected function boolFilterOnlyImportFailed28Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(28)
        ];
    }

    protected function getImportFeedFilteredIds(int $interval): array
    {
        $query = "SELECT imp.id
            FROM `import_feed` imp
            JOIN import_job imj ON imj.import_feed_id = imp.id
            WHERE imj.state = 'Failed'
            AND imj.start >= DATE_SUB(NOW(), INTERVAL $interval DAY)";

        return array_column(
            $this->getEntityManager()->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC),
            'id'
        );
    }
}
