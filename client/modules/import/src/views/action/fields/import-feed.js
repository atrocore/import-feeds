/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/action/fields/import-feed', 'views/fields/link', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                if (this.model.get('type') === 'import'){
                    this.model.set('payload', '{"sourceEntitiesIds": {{ sourceEntitiesIds|json_encode|raw}}}');
                }
            });
        },

    })
});