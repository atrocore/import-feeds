<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;

class ImportJob extends Base
{
    public function actionGenerateFile($params, \stdClass $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'id') || !property_exists($data, 'field')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if ($data->field === 'convertedFile') {
            return $this->getRecordService()->generateConvertedFile((string)$data->id);
        }

        if ($data->field === 'errorsAttachment') {
            return $this->getRecordService()->generateErrorsAttachment((string)$data->id);
        }

        return null;
    }

    public function actionGetImportJobsViaScope($params, $data, $request): array
    {
        if (!$request->isGet() || empty($request->get('scope'))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            return [];
        }

        return $this->getRecordService()->getImportJobsViaScope((string)$request->get('scope'));
    }

    /**
     * @inheritDoc
     */
    public function actionListLinked($params, $data, $request)
    {
        if ($params['link'] == 'importJobLogs') {
            $where = $request->get('where');
            $where[] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['error']
            ];
            $request->setQuery('where', $where);
        }

        return parent::actionListLinked($params, $data, $request);
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionMassUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionCreateLink($params, $data, $request)
    {
        throw new NotFound();
    }
}
