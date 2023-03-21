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
            'click [data-action="generateFile"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.actionGenerateFile($(e.currentTarget).data('name'));
            }
        }, Dep.prototype.events),

        setupActionItems: function () {
            console.log("just to test import");
            console.log(this.model);
            if (['Failed', 'Canceled'].includes(this.model.get('state'))) {
                this.dropdownItemList.push({
                    'name': 'tryAgainImportJob',
                    action: 'tryAgainImportJob',
                    label: 'tryAgain',
                });
            }
            Dep.prototype.setupActionItems.call(this);
        },

        actionGenerateFile(field) {
            this.notify(this.translate('generating', 'labels', 'ImportJob'));
            this.ajaxPostRequest('ImportJob/action/generateFile', {id: this.model.get('id'), field: field}).then(entity => {
                if (entity.id && entity.name){
                    this.model.set(field + 'Id', entity.id);
                    this.model.set(field + 'Name', entity.name);
                }
                this.notify('Done', 'success');
            });
        },
    })
);