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

namespace Import\Core\Utils;

class Util
{
    public static function generateCsvContents(array $data, string $delimiter = ',', string $enclosure = '"'): string
    {
        // prepare file name
        $fileName = 'data/tmp_' . \Espo\Core\Utils\Util::generateId() . '.csv';

        // create file
        $fp = fopen($fileName, 'w');
        foreach ($data as $fields) {
            fputcsv($fp, $fields, $delimiter, $enclosure);
        }
        fclose($fp);

        // get contents
        $contents = file_get_contents($fileName);

        // delete file
        unlink($fileName);

        return $contents;
    }
}
