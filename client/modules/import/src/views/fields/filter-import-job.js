/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/fields/filter-import-job', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            const scope = this.model.defs.fields.filterCreateImportJob.scope;

            this.params.options = [];
            this.translatedOptions = {};

            this.ajaxGetRequest(`ImportJob/action/getImportJobsViaScope`, {scope: scope}, {async: false}).then(list => {
                list.forEach(item => {
                    this.params.options.push(item.id);
                    this.translatedOptions[item.id] = item.name;
                });
            });

            Dep.prototype.setup.call(this);
        },

    })
);
