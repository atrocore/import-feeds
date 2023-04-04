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

Espo.define('import:views/import-feed/modals/run-import-options', 'views/modal',
    Dep => Dep.extend({

        template: 'import:import-feed/modals/run-import-options',

        data() {
            return {
                scope: this.scope
            }
        },

        setup() {
            this.buttonList = [
                {
                    name: 'runImport',
                    label: 'import',
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.scope = this.options.scope || this.model.name || this.scope;
            this.header = this.getLanguage().translate('import', 'labels', this.scope);
            this.setupFields();
        },

        setupFields() {
            this.createView('importFile', 'import:views/import-feed/fields/file', {
                el: `${this.options.el} .field[data-name="importFile"]`,
                model: this.model,
                name: 'importFile',
                params: {
                    required: true
                },
                mode: 'edit',
                inlineEditDisabled: true
            }, view => {
                this.listenTo(this, 'close', () => {
                    view.deleteAttachment();
                });
            });
        },

        actionRunImport() {
            if (this.validate()) {
                this.notify('Not valid', 'error');
                return;
            }

            let data = {
                importFeedId: this.model.id || null,
                attachmentId: this.model.get('importFileId') || null,
            };
            this.notify(this.translate('creatingImportJobs', 'labels', 'ImportFeed'));
            this.ajaxPostRequest('ImportFeed/action/runImport', data).then(response => {
                if (response) {
                    this.notify('Created', 'success');
                    this.dialog.close();
                    this.model.trigger('importRun');
                }
            });
        },

        validate() {
            let notValid = false;
            let fields = this.nestedViews;
            for (let i in fields) {
                if (fields[i].mode === 'edit') {
                    if (!fields[i].disabled && !fields[i].readOnly) {
                        notValid = fields[i].validate() || notValid;
                    }
                }
            }
            return notValid
        },
    })
);
