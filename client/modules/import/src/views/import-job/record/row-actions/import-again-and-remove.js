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

Espo.define('import:views/import-job/record/row-actions/import-again-and-remove', 'views/record/row-actions/remove-only', Dep => {

    return Dep.extend({

        getActionList() {
            let list = Dep.prototype.getActionList.call(this);

            if (['Failed', 'Canceled'].includes(this.model.get('state')) && this.options.acl.edit) {
                list.unshift({
                    action: 'tryAgainImportJob',
                    label: 'tryAgain',
                    data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        }
    });

});