/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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