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

Espo.define('import:views/import-job/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        buttonsDisabled: true,

        events: _.extend({
            'click [data-action="generateConvertedFile"]': function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.actionGenerateConvertedFile();
            }
        }, Dep.prototype.events),

        actionGenerateConvertedFile() {
            this.notify(this.translate('generating', 'labels', 'ImportJob'));
            this.ajaxPostRequest('ImportJob/action/generateConvertedFile', {id: this.model.get('id')}).then(convertedFile => {
                this.model.set('convertedFileId', convertedFile.id);
                this.model.set('convertedFileName', convertedFile.name);
                this.notify('Done', 'success');
            });
        },

    })
);