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

/**
 * Class DataSyncErrorsImportDashlet
 */
class DataSyncErrorsImportDashlet extends AbstractDashletService
{
    /**
     * Int Class
     */
    public function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }

    /**
     * Get Import failed feeds
     *
     * @return array
     * @throws \Espo\Core\Exceptions\Error
     */

    public function getDashlet(): array
    {
        $types = [
            ['name' => 'importErrorDuring24Hours', 'interval' => 1],
            ['name' => 'importErrorDuring7Days', 'interval' => 7],
            ['name' => 'importErrorDuring28Days', 'interval' => 28]
        ];

        foreach ($types as $type) {
            $data = $this->getImportData($type['interval']);
            $list[] = [
                'id'        => $this->getInjection('language')->translate($type['name']),
                'name'        => $this->getInjection('language')->translate($type['name']),
                'feeds'      => $data[0]['feeds'],
                'jobs'      => $data[0]['jobs'],
                'interval'     => $type['interval']
            ];
        }

        return ['total' => count($list), 'list' => $list];
    }

    protected function getImportData(int $interval): array
    {
        $query = "SELECT COUNT(*) AS jobs, COUNT(DISTINCT imp.id) as feeds
            FROM `import_feed` imp
            JOIN import_job imj ON imj.import_feed_id = imp.id
            WHERE imj.state = 'Failed'
            AND imj.start >= DATE_SUB(NOW(), INTERVAL $interval DAY)";

        return $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
