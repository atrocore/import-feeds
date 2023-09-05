/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/source-fields', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            ['file', 'sheet', 'format', 'fileFieldDelimiter', 'fileTextQualifier', 'isFileHeaderRow', 'excludedNodes', 'keptStringNodes'].forEach(fieldName => {
                let action = fieldName === 'file' ? 'fileUpdate' : 'change:' + fieldName;
                this.listenTo(this.model, action, () => {
                    if (this.getParentView().getView(fieldName).mode === 'edit') {
                        this.loadFileColumns();
                    }
                });
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail') {
                if (this.$el.height() > 300) {
                    this.$el.css('max-height', '300px');
                    this.$el.css('overflow-x', 'hidden');
                    this.$el.css('overflow-y', 'scroll');
                }
            }

            if (this.mode === 'detail' && ['JSON', 'XML'].includes(this.model.get('format'))) {
                let items = [];
                (this.model.get(this.name) || []).forEach(column => {
                    let parts = column.split('.');
                    let last = parts.pop();
                    if (parts.length === 0) {
                        items.push(last);
                    } else {
                        items.push('<span style="color: #bbb">' + parts.join('.') + '</span>.' + last);
                    }
                });

                this.$el.html(items.join(', '));
            }
        },

        readSourceFieldsFromJob(jobId) {
            this.ajaxGetRequest(`QueueItem/${jobId}`).success(queueItem => {
                if (queueItem.status === 'Canceled') {
                    $('.attachment-upload .remove-attachment').click();
                    this.model.set('sourceFields', []);
                    this.$el.html('');
                } else if (queueItem.status === 'Success') {
                    this.model.set('sourceFields', queueItem.data.sourceFields);
                } else {
                    setTimeout(() => {
                        this.readSourceFieldsFromJob(jobId);
                    }, 4000);
                }
            }).error(response => {
                $('.attachment-upload .remove-attachment').click();
                this.model.set('sourceFields', []);
                this.$el.html('');
            });
        },

        loadFileColumns() {
            this.model.set('sourceFields', [], {silent: true});

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
                excludedNodes: this.model.get('excludedNodes'),
                keptStringNodes: this.model.get('keptStringNodes'),
                isHeaderRow: this.model.get('isFileHeaderRow') ? 1 : 0,
                sheet: this.model.get('sheet')
            };

            this.ajaxPostRequest(`ImportFeed/action/ParseFileColumns`, data).success(response => {
                if (response.jobId) {
                    Backbone.trigger('showQueuePanel');
                    this.$el.html('<img alt="preloader" class="preloader" style="height:19px;margin-top:6px;margin-left:-8px" src="client/img/atro-loader.svg" />');
                    this.readSourceFieldsFromJob(response.jobId);
                } else {
                    this.model.set('sourceFields', response);
                }
            });
        },

    })
);