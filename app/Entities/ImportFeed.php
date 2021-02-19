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

/**
 * Class ImportFeed
 */
class ImportFeed extends \Espo\Core\Templates\Entities\Base
{
    /**
     * @var string
     */
    protected $entityType = "ImportFeed";

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
