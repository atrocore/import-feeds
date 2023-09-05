<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\FieldConverters;

use Espo\Core\Exceptions\BadRequest;

class Integer extends Varchar
{
    /**
     * @inheritDoc
     *
     * @throws \Exception
     */
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? null : $config['default'];
        if ($config['default'] === '0' || $config['default'] === 0) {
            $default = 0;
        }

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            $this->ignoreAttribute($value, $config);
            if (strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $value = $default;
            }
            if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }
        } else {
            $value = $default;
        }

        if ($value !== null) {
            $name = $config['name'];
            $inputRow->{$name} = $this->prepareIntValue((string)$value, $config);
        }
    }

    public function prepareIntValue(string $value, array $config): int
    {
        $thousandSeparator = (string)$config['thousandSeparator'];
        $decimalMark = $config['decimalMark'];

        $intValue = (int)str_replace($thousandSeparator, '', $value);
        $checkValueStrict = number_format((float)$intValue, 0, $decimalMark, $thousandSeparator);
        $checkValueUnStrict = number_format((float)$intValue, 0, $decimalMark, '');

        if (!in_array($value, [$checkValueStrict, $checkValueUnStrict])) {
            $type = $this->translate('int', 'fieldTypes', 'Admin');
            throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), $value, $type));
        }

        return (int)$intValue;
    }
}
