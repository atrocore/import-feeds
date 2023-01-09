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

Espo.define('import:views/import-feed/fields/file', 'views/fields/file',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.name === 'file') {
                this.listenTo(this.model, 'change:fileId', () => {
                    this.model.trigger('fileUpdate');
                });

                this.prepareAccept();
                this.listenTo(this.model, 'change:format', () => {
                    this.model.trigger('fileUpdate');
                    this.prepareAccept();
                    this.reRender();
                });
            }
        },

        prepareAccept() {
            if (this.model.get('format') === 'CSV') {
                this.acceptAttribue = ['.csv'];
            }

            if (this.model.get('format') === 'Excel') {
                this.acceptAttribue = ['.xls', '.xlsx'];
            }

            if (this.model.get('format') === 'JSON') {
                this.acceptAttribue = ['.json'];
            }

            if (this.model.get('format') === 'XML') {
                this.acceptAttribue = ['.xml'];
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit' && this.model.get('format') === 'CSV') {
                this.$el.find('.attachment-upload .attachment-button .pull-left').append('<div class="text-muted small">UTF-8</div>');
            }
        },

    })
);