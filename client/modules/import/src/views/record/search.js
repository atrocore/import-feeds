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

Espo.define('import:views/record/search', 'class-replace!import:views/record/search', Dep => Dep.extend({

    createFilters(callback) {
        this.modifySearchModel();
        Dep.prototype.createFilters.call(this, callback);
    },

    modifySearchModel() {
        const additionalDefs = [
            {
                name: 'createdByImport',
                field: {
                    type: 'link',
                    readOnly: true
                },
                link: {
                    entity: 'ImportJob',
                    type: "belongsTo"
                }
            },
            {
                name: 'updatedByImport',
                field: {
                    type: 'link',
                    readOnly: true
                },
                link: {
                    entity: 'ImportJob',
                    type: "belongsTo"
                }
            }
        ];

        additionalDefs.forEach(item => {
            if (!(item.name in this.model.defs.fields)) {
                this.model.defs.fields[item.name] = item.field;
            }
            if (!(item.name in this.model.defs.links)) {
                this.model.defs.links[item.name] = item.link;
            }
        });
    }

}));
