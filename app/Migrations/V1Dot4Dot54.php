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

namespace Import\Migrations;

use Treo\Core\Migration\Base;

class V1Dot4Dot54 extends Base
{
    public function up(): void
    {
        $this->execute("DROP TABLE scheduled_job_import_feed");
        $this->execute("DROP INDEX IDX_208D5E79A71ECAB0 ON scheduled_job_import_feed");
        $this->execute("DROP INDEX IDX_208D5E7942B515EF ON scheduled_job_import_feed");
        $this->execute("DROP INDEX UNIQ_208D5E79A71ECAB042B515EF ON scheduled_job_import_feed");

        $this->getPDO()->exec("CREATE TABLE scheduled_job_import_feed (id INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`, scheduled_job_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, import_feed_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, INDEX IDX_208D5E79A71ECAB0 (scheduled_job_id), INDEX IDX_208D5E7942B515EF (import_feed_id), UNIQUE INDEX UNIQ_208D5E79A71ECAB042B515EF (scheduled_job_id, import_feed_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

        $countMaximumHours = $this->getPDO()->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'scheduled_job' AND column_name = 'maximum_hours_to_look_back'")->fetchColumn();
        if ($countMaximumHours == 0) {
            $this->execute("ALTER TABLE scheduled_job ADD maximum_hours_to_look_back DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        }

        $this->execute("DROP INDEX id ON scheduled_job_import_feed");
    }

    public function down(): void
    {

        $this->execute("DROP INDEX IDX_208D5E79A71ECAB0 ON scheduled_job_import_feed");
        $this->execute("DROP INDEX IDX_208D5E7942B515EF ON scheduled_job_import_feed");
        $this->execute("DROP INDEX UNIQ_208D5E79A71ECAB042B515EF ON scheduled_job_import_feed");

        $this->execute("DROP TABLE scheduled_job_import_feed");
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
