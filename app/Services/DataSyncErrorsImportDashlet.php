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
                'feeds'      => $data['feeds'],
                'jobs'      => $data['jobs'],
                'interval'     => $type['interval']
            ];
        }

        return ['total' => count($list), 'list' => $list];
    }

    protected function getImportData(int $interval): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $res = $connection->createQueryBuilder()
            ->select('COUNT(imj.id) AS jobs, COUNT(DISTINCT imp.id) as feeds')
            ->from($connection->quoteIdentifier('import_feed'), 'imp')
            ->innerJoin('imp', $connection->quoteIdentifier('import_job'), 'imj', 'imj.import_feed_id = imp.id')
            ->where('imj.state = :state')
            ->andWhere('imj.start >= :start')
            ->setParameter('state', 'Failed')
            ->setParameter('start', (new \DateTime())->modify("-{$interval} days")->format('Y-m-d H:i:s'))
            ->fetchAssociative();

        return empty($res) ? ['jobs' => 0, 'feeds' => 0] : $res;
    }
}
