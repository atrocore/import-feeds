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

namespace Import\Controllers;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Templates\Controllers\Base;

class ImportJobLog extends Base
{
    /**
     * @inheritDoc
     */
    public function actionList($params, $data, $request)
    {
        // prepare request
        $where = $request->get('where');
        $where[] = [
            'type'      => 'in',
            'attribute' => 'type',
            'value'     => ['error']
        ];
        $request->setQuery('where', $where);

        return parent::actionList($params, $data, $request);
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionDelete($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionMassUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionMassDelete($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionCreateLink($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @param array  $params
     * @param array  $data
     * @param object $request
     *
     * @throws NotFound
     */
    public function actionRemoveLink($params, $data, $request)
    {
        throw new NotFound();
    }
}
