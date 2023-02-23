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

Espo.define('import:views/import-feed/fields/sheet', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            this.listenTo(this.model, 'fileUpdate', () => {
                if (this.getParentView().getView('file').mode === 'edit') {
                    this.loadFileSheets();
                }
            });

            this.params.options = [];
            this.translatedOptions = {};

            (this.model.get('sheetOptions') || []).forEach((value, key)=> {
                this.translatedOptions[key] = value;
                this.params.options.push(key);
            })

            Dep.prototype.setup.call(this);
        },
        loadFileSheets() {

            let fileId = this.model.get('fileId');
            if (!fileId) {
                return;
            }

            let data = {
                attachmentId: fileId,
                format: this.model.get('format')
            };

            this.ajaxPostRequest(`ImportFeed/action/GetFileSheets`, data).success(response => {
                this.model.set('sheetOptions', response);
                this.params.options = [];
                this.translatedOptions = {};
                (response || []).forEach((value, key)=> {
                    this.translatedOptions[key] = value;
                    this.params.options.push(key);
                })

                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
        },
    })
);