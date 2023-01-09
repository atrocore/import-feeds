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