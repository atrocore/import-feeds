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

namespace Import\FileParsers;

use Atro\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\Attachment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Excel extends Csv
{
    public function getFileColumns(Attachment $attachment): array
    {
        $data = $this->data['fileData'] ?? null;

        if ($data === null) {
            $data = $this->getFileData($attachment, 0, 2);
        }

        return parent::getFileColumns($attachment);
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

    public function getFileData(Attachment $attachment, int $offset = 0, ?int $limit = null): array
    {
        $sheet = $this->data['sheet'] ?? 0;

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

        return $this->getInjection('eventManager')
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'excel']))
            ->getArgument('data');
    }

    public function createFile(string $fileName, array $data): void
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

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($fileName);
    }

    public function convertToUTF8(string $filename): void
    {
    }
}
