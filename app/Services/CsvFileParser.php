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
use Espo\Entities\Attachment;

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
        // prepare path
        $path = $this->getLocalFilePath($attachment);

        if ($delimiter === '\t') {
            $delimiter = "\t";
        }

        if ($offset === null) {
            $data = $this->readLineByLine($path, $delimiter, $enclosure, $limit);
        } else {
            $data = $this->getParsedFileData($path, $delimiter, $enclosure, $offset, $limit);
        }

        return $this
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'csv']))
            ->getArgument('data');
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

    protected function getParsedFileData(string $path, string $delimiter = ";", string $enclosure = '"', int $offset = 0, int $limit = null): array
    {
        // prepare result
        $result = [];

        if (file_exists($path) && ($handle = fopen($path, "r")) !== false) {
            $row = 0;
            while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false && (is_null($limit) || count($result) < $limit)) {
                if ($row >= $offset) {
                    $result[] = $data;
                }

                // increase
                $row++;
            }
            fclose($handle);
        }

        return $result;
    }

    protected function readLineByLine(string $path, string $delimiter = ";", string $enclosure = '"', int $limit = 200): array
    {
        if (!isset($this->fileHandles[$path])) {
            $this->fileHandles[$path] = fopen($path, "r");
        }

        $handle = $this->fileHandles[$path];

        $result = [];
        if ($handle !== false) {
            while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false && count($result) < $limit) {
                $result[] = $data;
            }
        }

        if (empty($result) || count($result) < $limit) {
            fclose($this->fileHandles[$path]);
            unset($this->fileHandles[$path]);
        }

        return $result;
    }
}
