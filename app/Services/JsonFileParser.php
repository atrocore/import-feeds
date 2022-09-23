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

use Espo\Core\Templates\Services\HasContainer;
use Espo\Entities\Attachment;

class JsonFileParser extends HasContainer
{
    public function getFileColumns(Attachment $attachment): array
    {
        $data = $this->getFileData($attachment);
        if (empty($data[0])) {
            return [];
        }

        return array_keys($data[0]);
    }

    public function getFileData(Attachment $attachment): array
    {
        $contents = file_get_contents($attachment->getFilePath());

        if (empty($contents)) {
            return [];
        }

        return \Import\Core\Utils\JsonToVerticalArray::mutate($contents);
    }
}
