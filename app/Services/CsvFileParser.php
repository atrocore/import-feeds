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

        $data = [];

        $file = fopen($path, 'r');

        $row = 0;
        while (($rowData = fgetcsv($file, 0, $delimiter, $enclosure)) !== false && (is_null($limit) || count($data) < $limit)) {
            if ($row >= $offset) {
                $data[] = $rowData;
            }
            $row++;
        }
        fclose($file);

        return $this
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'csv']))
            ->getArgument('data');
    }

    public function createFile(string $fileName, array $data, array $conf = []): void
    {
        $this->createDir($fileName);

        $fp = fopen($fileName, 'w');
        foreach ($data as $fields) {
            fputcsv($fp, $fields, $conf['delimiter'] ?? ',', $conf['enclosure'] ?? '"');
        }
        fclose($fp);

        // convert to utf-8
        $this->convertToUTF8($fileName);
    }

    public function convertAttachmentToUTF8(Attachment $attachment): void
    {
        $this->convertToUTF8($this->getLocalFilePath($attachment));
    }

    public function convertToUTF8(string $filename): void
    {
        $file = fopen($filename, 'r');

        $tempFilename = tempnam(sys_get_temp_dir(), 'utf8tmp');
        $tempFile = fopen($tempFilename, 'w');

        while (($line = fgets($file)) !== false) {
            $originalEncoding = mb_detect_encoding($line, mb_detect_order(), true);

            if ($originalEncoding === false || $originalEncoding === 'UTF-8') {
                fclose($file);
                fclose($tempFile);
                return;
            }

            $utf8Line = mb_convert_encoding($line, 'UTF-8', $originalEncoding);

            fwrite($tempFile, $utf8Line);
        }

        fclose($file);
        fclose($tempFile);

        rename($tempFilename, $filename);
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
