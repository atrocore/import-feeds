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

namespace Import\Services;

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\Attachment;

class ExcelFileParser extends CsvFileParser
{
    public function getFileColumns(Attachment $attachment, string $delimiter = ";", string $enclosure = '"', bool $isFileHeaderRow = true, array $data = null, int $sheet = 0
    ): array {
        if ($data === null) {
            $data = $this->getFileData($attachment, $delimiter, $enclosure, 0, 2, $sheet);
        }

        return parent::getFileColumns($attachment, $delimiter, $enclosure, $isFileHeaderRow, $data);
    }

    public function getFileSheetsNames(Attachment $attachment)
    {
        $path = $this->getLocalFilePath($attachment);
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        try {
            $data = $reader->load($path)->getSheetNames();
        } catch (\Throwable $e) {
            $data = [];
        }

        return $data;
    }

    public function getFileData(Attachment $attachment, string $delimiter = ";", string $enclosure = '"', ?int $offset = 0, int $limit = null, int $sheet = 0): array
    {
        $path = $this->getLocalFilePath($attachment);

        if (!file_exists($path)) {
            throw new BadRequest("File '$path' does not exist.");
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $rowNumber = 0;

        $data = [];

        $worksheet = $reader->load($path)->getSheet($sheet);
        foreach ($worksheet->getRowIterator() as $row) {
            $dataRow = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $dataRow[] = $cell->getValue();
            }

            if ($limit !== null && count($data) >= $limit) {
                break;
            }

            if ($offset === null || $rowNumber >= $offset) {
                $data[] = $dataRow;
            }
            $rowNumber++;
        }

        return $this
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'excel']))
            ->getArgument('data');
    }
}
