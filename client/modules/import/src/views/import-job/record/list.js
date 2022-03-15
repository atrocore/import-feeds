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

Espo.define('import:views/import-job/record/list', 'views/record/list',
    Dep => Dep.extend({

        rowActionsView: 'views/record/row-actions/view-and-remove',

        getSelectAttributeList: function (callback) {
            Dep.prototype.getSelectAttributeList.call(this, attributeList => {
                if (Array.isArray(attributeList) && !attributeList.includes('entityName')) {
                    attributeList.push('entityName', 'importFeedId');
                }
                callback(attributeList);
            });
        },

    })
);
