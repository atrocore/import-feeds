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

Espo.define('import:views/import-configurator-item/fields/column', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            this.params.options = this.model.get('sourceFields') || [];
            this.translatedOptions = {};

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.model.set('column', null);
            });

            this.listenTo(this.model, 'change:column change:attributeType', (model, data, additional) => {
                if (additional.skipColumnListener) {
                    return;
                }

                let type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`) || 'varchar';

                if (this.model.get('type') === 'Attribute') {
                    type = this.model.get('attributeType');
                }

                const types = {linkMultiple: 999, link: 999, currency: 2, int: 2, float: 2};
                const maxLength = types[type] ? types[type] : 1;

                if (this.model.get('column') && this.model.get('column').length > maxLength) {
                    let items = Espo.Utils.clone(this.model.get('column'));

                    let promise = new Promise((resolve, reject) => {
                        while (items.length > maxLength) {
                            items.shift();
                            if (items.length === maxLength) {
                                resolve();
                            }
                        }
                    });

                    promise.then(() => {
                        this.model.set('column', items, {skipColumnListener: true});
                        this.reRender();
                    });
                }
            });

            this.listenTo(this.model, 'change:default change:defaultId change:defaultIds', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list') {
                let originalValue = this.model.get(this.name) || [];

                let items = [];
                originalValue.forEach(column => {
                    let parts = column.split('.');
                    let last = parts.pop();
                    items.push(last);
                });

                this.$el.html('<span title="' + originalValue.join(', ') + '">' + items.join(', ') + '</span>');
            }
        },

        isRequired: function () {
            return this.params.options.length > 0 && !this.model.get('default') && !this.model.get('defaultId') && !this.model.get('defaultIds');
        },
    })
);