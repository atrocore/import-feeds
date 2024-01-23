<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\FieldConverters;

class File extends Varchar
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        parent::convert($inputRow, $config, $row);

        $inputRow->{$config['name'] . 'Id'} = $inputRow->{$config['name']};
        unset($inputRow->{$config['name']});
    }
}
