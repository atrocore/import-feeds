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

Espo.define('import:views/fields/int-with-link-to-list', 'views/fields/int',
    Dep => Dep.extend({

        listTemplate: 'import:fields/int-with-link-to-list/list',

        detailTemplate: 'import:fields/int-with-link-to-list/detail',

        listScope: '',

        events: _.extend({
            'click [data-action="showList"]': function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.actionShowList();
            }
        }, Dep.prototype.events),

        actionShowList() {
            const searchFilter = this.getSearchFilter();
            this.getStorage().set('listSearch', this.listScope, searchFilter);
            window.open(`#${this.listScope}`, '_blank');
        },

        getSearchFilter() {
            return {};
        }

    })
);
