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

use Espo\Core\SelectManagers\Base;

class ImportJobLog extends Base
{
    protected function access(&$result)
    {
        $importJobs = $this->getEntityManager()->getRepository('ImportJob')
            ->select(['id'])
            ->find($this->createSelectManager('ImportJob')->getSelectParams([], true, true));

        $result['whereClause'][] = ['OR' => ['importJobId' => array_column($importJobs->toArray(), 'id')]];
    }
}
