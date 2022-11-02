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

Espo.define('import:views/import-feed/fields/unused-columns', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            ['file', 'fileFieldDelimiter', 'fileTextQualifier', 'isFileHeaderRow'].forEach(fieldName => {
                let action = fieldName === 'file' ? 'fileUpdate' : 'change:' + fieldName;
                this.listenTo(this.model, action, () => {
                    if (this.getParentView().getView(fieldName).mode === 'edit') {
                        this.loadFileColumns();
                    }
                });
            });

            this.loadUnusedColumns();
            this.listenTo(this.model, 'change:allColumns after:relate after:save', () => {
                this.loadUnusedColumns();
            });

            this.listenTo(this.model, 'updateUnusedColumns', () => {
                setTimeout(() => this.loadUnusedColumns(), 1000);
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' && ['JSON', 'XML'].includes(this.model.get('format'))) {
                let items = [];
                (this.model.get(this.name) || []).forEach(column => {
                    let parts = column.split('.');
                    let last = parts.pop();
                    items.push('<span style="color: #bbb">' + parts.join('.') + '</span>.' + last);
                });

                this.$el.html(items.join(', '));
            }
        },

        loadUnusedColumns() {
            const allColumns = this.model.get('allColumns') || [];

            if (!this.model.get('id') || allColumns === []) {
                this.model.set('unusedColumns', allColumns);
                this.reRender();
                return;
            }

            this.ajaxGetRequest(`ImportFeed/${this.model.get('id')}/configuratorItems`).success(response => {
                let usedColumns = [];
                (response.list || []).forEach(item => {
                    (item.column || []).forEach(column => {
                        usedColumns.push(column);
                    });
                });

                let unusedColumns = [];

                allColumns.forEach(column => {
                    if (!usedColumns.includes(column)) {
                        unusedColumns.push(column);
                    }
                })

                this.model.set('unusedColumns', unusedColumns);
                this.reRender();
            });
        },

        readAllColumnsFromJob(jobId) {
            this.ajaxGetRequest(`QueueItem/${jobId}`).success(queueItem => {
                if (queueItem.status === 'Canceled') {
                    $('.attachment-upload .remove-attachment').click();
                    this.model.set('allColumns', []);
                    this.$el.html('');
                } else if (queueItem.status === 'Success') {
                    this.model.set('allColumns', queueItem.data.allColumns);
                } else {
                    setTimeout(() => {
                        this.readAllColumnsFromJob(jobId);
                    }, 4000);
                }
            }).error(response => {
                $('.attachment-upload .remove-attachment').click();
                this.model.set('allColumns', []);
                this.$el.html('');
            });
        },

        loadFileColumns() {
            this.model.set('allColumns', []);

            let fileId = this.model.get('fileId');
            if (!fileId) {
                return;
            }

            let data = {
                importFeedId: this.model.get('id'),
                attachmentId: fileId,
                format: this.model.get('format'),
                delimiter: this.model.get('fileFieldDelimiter'),
                enclosure: this.model.get('fileTextQualifier'),
                isHeaderRow: this.model.get('isFileHeaderRow') ? 1 : 0
            };

            this.ajaxPostRequest(`ImportFeed/action/ParseFileColumns`, data).success(response => {
                if (response.jobId) {
                    Backbone.trigger('showQueuePanel');
                    this.$el.html('<img alt="preloader" class="preloader" style="height:19px;margin-top:6px;margin-left:-8px" src="client/img/atro-loader.svg" />');
                    ;
                    this.readAllColumnsFromJob(response.jobId);
                } else {
                    this.model.set('allColumns', response);
                }
            });
        },

    })
);