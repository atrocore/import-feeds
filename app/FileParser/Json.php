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

namespace Import\FileParser;

use Espo\Core\EventManager\Event;
use Espo\Core\Injectable;
use Espo\Entities\Attachment;

class Json extends Injectable implements FileParserInterface
{
    protected array $data = [];
    protected array $excludedNodes = [];
    protected array $keptStringNodes = [];

    public function __construct()
    {
        $this->addDependency('eventManager');
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getFileColumns(Attachment $attachment): array
    {
        $this->excludedNodes = $this->data['excludedNodes'] ?? [];
        $this->keptStringNodes = $this->data['keptStringNodes'] ?? [];

        $data = $this->getFileData($attachment);
        if (empty($data[0])) {
            return [];
        }

        return array_keys($data[0]);
    }

    public function getFileData(Attachment $attachment, int $offset = 0, ?int $limit = null): array
    {
        $contents = file_get_contents($attachment->getFilePath());

        if (empty($contents)) {
            return [];
        }

        $payload = array_merge(['data' => ['excludedNodes' => $this->excludedNodes, 'keptStringNodes' => $this->keptStringNodes]], $this->data);

        $data = \Import\Core\Utils\JsonToVerticalArray::mutate($contents, $payload);

        return $this->getInjection('eventManager')
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'json']))
            ->getArgument('data');
    }

    public function createFile(string $fileName, array $data): void
    {
        $this->createDir($fileName);
        file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT));
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
