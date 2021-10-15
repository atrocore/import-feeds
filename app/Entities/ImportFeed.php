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

namespace Import\Entities;

use Espo\Core\Templates\Entities\Base;
use Espo\Core\Utils\Json;

class ImportFeed extends Base
{
    /**
     * @var string
     */
    protected $entityType = "ImportFeed";

    public function getFeedField(string $name)
    {
        $data = $this->getFeedFields();

        return isset($data[$name]) ? $data[$name] : null;
    }

    public function getFeedFields(): array
    {
        if (!empty($data = $this->get('data'))) {
            $data = Json::decode(Json::encode($data), true);
            if (!empty($data['feedFields']) && is_array($data['feedFields'])) {
                return $data['feedFields'];
            }
        }

        return [];
    }

    /**
     * @return string
     */
    public function getDelimiter(): string
    {
        return (!empty($this)) ? (string)$this->get('fileFieldDelimiter') : ";";
    }

    /**
     * @return string
     */
    public function getEnclosure(): string
    {
        return (!empty($this) && $this->get('fileTextQualifier') == 'singleQuote') ? "'" : '"';
    }

    /**
     * @return bool
     */
    public function isFileHeaderRow(): bool
    {
        return (!empty($this) && !empty($this->get('isFileHeaderRow'))) ? true : false;
    }
}
