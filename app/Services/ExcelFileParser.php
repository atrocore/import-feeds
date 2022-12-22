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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Import\Services;

use Espo\Entities\Attachment;

class ExcelFileParser extends CsvFileParser
{
    public function getFileData(Attachment $attachment, string $delimiter = ";", string $enclosure = '"', int $offset = 0, int $limit = null): array
    {
        $path = $this->getLocalFilePath($attachment);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        try {
            $data = $reader->load($path)->getSheet(0)->toArray();
        } catch (\Throwable $e) {
            $data = [];
        }

        unset($reader);

        $result = [];
        foreach ($data as $k => $row) {
            if ($k < $offset) {
                continue 1;
            }
            $result[] = $row;
        }

        unset($data);

        if (!empty($limit)) {
            $limited = [];
            foreach ($result as $v) {
                if (count($limited) >= $limit) {
                    break;
                }
                $limited[] = $v;
            }
            $result = $limited;
            unset($limited);
        }

        return $result;
    }
}
