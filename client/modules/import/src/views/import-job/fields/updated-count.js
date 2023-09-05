/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-job/fields/updated-count', 'import:views/fields/int-with-link-to-list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listScope = this.model.get('entityName');
        },

        getSearchFilter() {
            return {
                textFilter: '',
                primary: null,
                presetName: null,
                bool: {},
                advanced: {
                    'filterUpdateImportJob-1': {
                        type: 'in',
                        value: [this.model.id],
                        data: {
                            type: 'anyOf',
                            valueList: [this.model.id]
                        }
                    }
                }
            };
        }

    })
);
