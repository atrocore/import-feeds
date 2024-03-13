/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/record/search', 'class-replace!import:views/record/search', Dep => Dep.extend({

    createFilters(callback) {
        this.modifySearchModel();
        Dep.prototype.createFilters.call(this, callback);
    },

    modifySearchModel() {
        const additionalDefs = [
            {
                name: 'createdByImport',
                field: {
                    type: 'link',
                    readOnly: true
                },
                link: {
                    entity: 'ImportJob',
                    type: "belongsTo"
                }
            },
            {
                name: 'updatedByImport',
                field: {
                    type: 'link',
                    readOnly: true
                },
                link: {
                    entity: 'ImportJob',
                    type: "belongsTo"
                }
            }
        ];

        additionalDefs.forEach(item => {
            if (!(item.name in this.model.defs.fields)) {
                this.model.defs.fields[item.name] = item.field;
            }
            if (!(item.name in this.model.defs.links)) {
                this.model.defs.links[item.name] = item.link;
            }
        });
    }

}));
