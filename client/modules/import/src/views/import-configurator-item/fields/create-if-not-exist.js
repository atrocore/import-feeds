/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/create-if-not-exist', 'views/fields/bool',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.reRender();
            });

            this.listenTo(this.model, 'change:importBy', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`);
            if (['image', 'asset', 'link', 'linkMultiple'].includes(type)) {
                const $input = this.$el.find('input');

                let foreignEntity = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.links.${this.model.get('name')}.entity`);
                if (type === 'asset'){
                    foreignEntity = 'Asset';
                }

                /**
                 * For Main Image
                 */
                if (this.model.get('name') === 'mainImage' || ['Product', 'Category'].includes(this.model.get('entity')) && this.model.get('name') === 'image') {
                    foreignEntity = 'Asset';
                }

                if (['Asset', 'Attachment'].includes(foreignEntity)) {
                    $input.attr('disabled', 'disabled');
                    this.model.set('createIfNotExist', (this.model.get('importBy') || []).includes('url'));
                } else {
                    $input.removeAttr('disabled');
                }
                this.show();
            } else {
                this.hide();
            }
        },

    })
);