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
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class CsvFileParser extends AbstractFileParser
{
    protected array $fileHandles = [];

    public function getFileColumns(Attachment $attachment, string $delimiter = ";", string $enclosure = '"', bool $isFileHeaderRow = true, array $data = null): array
    {
        // prepare result
        $result = [];

        // get data
        if ($data === null) {
            $data = $this->getFileData($attachment, $delimiter, $enclosure, 0, 2);
        }

        if (isset($data[0])) {
            if ($isFileHeaderRow && isset($data[1])) {
                foreach ($data[0] as $k => $value) {
                    $value = trim((string)$value);
                    if (empty($value) && $value !== '0' && $value !== 0) {
                        $value = self::createColumnName($k, $data);
                    }
                    $result[] = $this->removeNonPrintableCharacters($value);
                }
            } else {
                foreach ($data[0] as $k => $value) {
                    $result[] = $this->removeNonPrintableCharacters(self::createColumnName($k, $data));
                }
            }
        }

        return $result;
    }

    public function getFileData(Attachment $attachment, string $delimiter = ";", string $enclosure = '"', ?int $offset = 0, ?int $limit = null): array
    {
        $path = $this->getLocalFilePath($attachment);

        if (!file_exists($path)) {
            throw new BadRequest("File '$path' does not exist.");
        }

        if ($delimiter === '\t') {
            $delimiter = "\t";
        }

        $reader = new Csv();
        $reader->setDelimiter($delimiter);
        $reader->setEnclosure($enclosure);

        $rowNumber = 0;

        $data = [];
        $worksheet = $reader->load($path)->getActiveSheet();
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
                $skip = true;
                foreach ($dataRow as $v) {
                    if ($v !== null) {
                        $skip = false;
                        break;
                    }
                }
                if (!$skip) {
                    $data[] = $dataRow;
                }
            }
            $rowNumber++;
        }

        return $this
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'csv']))
            ->getArgument('data');
    }

    public function createFile(string $fileName, array $data, array $conf = []): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;
        foreach ($data as $rowData) {
            $column = 1;
            foreach ($rowData as $cellData) {
                $sheet->setCellValueByColumnAndRow($column, $row, $cellData);
                $column++;
            }
            $row++;
        }

        $this->createDir($fileName);

        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setDelimiter($conf['delimiter'] ?? ',');
        $writer->setEnclosure($conf['enclosure'] ?? '"');
        $writer->save($fileName);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function removeNonPrintableCharacters(string $value): string
    {
        return preg_replace('/[\x00-\x1f]/', '', $value);
    }

    protected static function createColumnName(int $k, array $data): string
    {
        $value = (string)($k + 1);
        if (isset($data[1][$k])) {
            $firstRowValue = trim((string)$data[1][$k]);
            if (empty($firstRowValue) && $firstRowValue !== '0' && $firstRowValue !== 0) {
                return $value;
            }
            $cropped = mb_substr($firstRowValue, 0, 24);
            $value .= ' ‹ ' . $cropped;

            if ($firstRowValue != $cropped) {
                $value .= '...';
            }
        }

        return $value;
    }

    public function getLocalFilePath(Attachment $attachment): string
    {
        $path = $this
            ->getContainer()
            ->get('fileStorageManager')
            ->getLocalFilePath($attachment);

        return (empty($path)) ? '' : (string)$path;
    }

    protected function createDir(string $fileName): void
    {
        $parts = explode('/', $fileName);
        array_pop($parts);
        $dir = implode('/', $parts);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            sleep(1);
        }
    }
}
