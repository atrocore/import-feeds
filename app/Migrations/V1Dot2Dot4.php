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

namespace Import\Migrations;

class V1Dot2Dot4 extends \Treo\Core\Migration\Base
{
    public function up(): void
    {
        try {
            $this->getPDO()->exec("ALTER TABLE `import_feed` ADD jobs_max INT DEFAULT '10' COLLATE utf8mb4_unicode_ci");
            $this->getPDO()->exec("UPDATE `import_feed` SET `jobs_max`=10 WHERE 1");
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $this->getPDO()->exec("ALTER TABLE `scheduled_job` ADD import_feed_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
            $this->getPDO()->exec("CREATE INDEX IDX_IMPORT_FEED_ID ON `scheduled_job` (import_feed_id)");
        } catch (\Throwable $e) {
            // ignore
        }

        $container = (new \Treo\Core\Application())->getContainer();
        $em = $container->get('entityManager');

        $auth = new \Espo\Core\Utils\Auth($container);
        $auth->useNoAuth();

        foreach ($em->getRepository('ImportFeed')->where(['type' => 'simple'])->find() as $feed) {
            $attachment = $feed->get('file');
            if (empty($attachment)) {
                continue;
            }

            $delimiter = (!empty($feed->getFeedField('fileFieldDelimiter'))) ? $feed->getFeedField('fileFieldDelimiter') : ';';
            $enclosure = ($feed->getFeedField('fileTextQualifier') == 'singleQuote') ? "'" : '"';
            $isFileHeaderRow = (is_null($feed->getFeedField('isHeaderRow'))) ? true : !empty($feed->getFeedField('isHeaderRow'));

            try {
                $allColumns = $container->get('serviceFactory')->create('CsvFileParser')->getFileColumns($attachment, $delimiter, $enclosure, $isFileHeaderRow);
                $feed->setFeedField('allColumns', $allColumns);
                $em->saveEntity($feed);

                $items = $feed->get('configuratorItems');
                if (empty($items) || count($items) == 0) {
                    continue;
                }

                foreach ($items as $item) {
                    if (!empty($columns = $item->get('column'))) {
                        $newColumns = [];
                        foreach ($columns as $column) {
                            if (isset($allColumns[$column])) {
                                $newColumns[] = $allColumns[$column];
                            }
                        }
                        $item->set('column', $newColumns);
                        $em->saveEntity($item);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        $this->getPDO()->exec("DELETE FROM `import_feed` WHERE 1");
        $this->getPDO()->exec("DELETE FROM `import_configurator_item` WHERE 1");
    }
}
