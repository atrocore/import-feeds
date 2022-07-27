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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('import:views/import-configurator-item/fields/default-container', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'import:import-configurator-item/fields/value-container/base',

        editTemplate: 'import:import-configurator-item/fields/value-container/base',

        typesWithDefaultHash: ['password', 'text', 'varchar'],

        setup() {
            this.defs = this.options.defs || {};
            this.name = this.options.name || this.defs.name;
            this.params = this.options.params || this.defs.params || {};

            this.createDefaultField();

            if (this.mode === 'edit') {
                this.listenTo(this.model, 'change:attributeId', () => {
                    if (this.model.get('attributeId')) {
                        this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).then(attribute => {
                            this.model.set('attributeType', attribute.type);
                            this.model.set('attributeTypeValue', attribute.typeValue || []);

                            this.clearDefaultField();
                            this.createDefaultField();
                        });
                    }
                });

                this.listenTo(this.model, 'change:name change:createIfNotExist', () => {
                    this.clearDefaultField();
                    this.createDefaultField();
                });

                this.listenTo(this.model, 'change:defaultId change:defaultIds', () => {
                    if (!this.model.get('defaultId') && !this.model.get('defaultIds')) {
                        this.model.set('default', null);
                    }
                });
            }
        },

        clearDefaultField() {
            this.model.set('default', null);

            if (this.model.attributes.defaultCurrency) {
                delete this.model.attributes.defaultCurrency;
            }

            if (this.model.attributes.defaultUnit) {
                delete this.model.attributes.defaultUnit;
            }

            if (this.model.attributes.defaultId) {
                delete this.model.attributes.defaultId;
            }

            if (this.model.attributes.defaultName) {
                delete this.model.attributes.defaultName;
            }

            if (this.model.attributes.defaultIds) {
                delete this.model.attributes.defaultIds;
            }

            if (this.model.attributes.defaultNames) {
                delete this.model.attributes.defaultNames;
            }

            if (this.model.defs.links.default) {
                delete this.model.defs.links.default;
            }

            // clear view
            this.clearView('default');
        },

        prepareDefaultModel(type, options) {
            if (type === 'link') {
                this.model.defs.links["default"] = {
                    type: 'belongsTo',
                    entity: this.getMetadata().get(`entityDefs.${this.model.get('entity')}.links.${this.model.get('name')}.entity`)
                };
            }

            if (type === 'linkMultiple') {
                this.model.defs.links["default"] = {
                    type: 'hasMany',
                    entity: this.getMetadata().get(`entityDefs.${this.model.get('entity')}.links.${this.model.get('name')}.entity`)
                };
            }

            if (type === 'enum' || type === 'multiEnum') {
                this.params.options = options;
                this.params.translatedOptions = {};
                options.forEach(option => {
                    this.params.translatedOptions[option.toString()] = this.translate(option, 'labels', this.model.get('entity')) || option;
                });
            }

            if (type === 'unit') {
                if (this.model.get('type') === 'Attribute') {
                    this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`, null, {async: false}).then(attribute => {
                        this.model.defs.fields["default"] = {measure: attribute.typeValue[0]};
                    });
                } else {
                    this.model.defs.fields["default"] = {measure: this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measure`)};
                }
            }
        },

        createDefaultField() {
            let type = 'varchar';

            let options = [];

            if (this.model.get('type') === 'Field') {
                type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`) || 'varchar';
                options = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.options`) || [];
            }

            if (this.model.get('type') === 'Attribute') {
                type = this.model.get('attributeType');
                options = this.model.get('attributeTypeValue') || [];
            }

            this.prepareDefaultModel(type, options);

            if (this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`) === 'asset') {
                type = 'link';
                this.model.defs.links["default"] = {
                    type: 'belongsTo',
                    entity: 'Asset'
                };
            }

            /**
             * For Main Image
             */
            if (this.model.get('name') === 'mainImage' || ['Product', 'Category'].includes(this.model.get('entity')) && this.model.get('name') === 'image') {
                type = 'link';
                this.model.defs.links["default"] = {
                    type: 'belongsTo',
                    entity: 'Asset'
                };
            }

            this.createView('default', this.getFieldManager().getViewName(type), {
                el: `${this.options.el} > .field[data-name="default"]`,
                model: this.model,
                name: 'default',
                mode: this.mode,
                defs: this.defs,
                params: this.params,
                inlineEditDisabled: true,
                createDisabled: true,
                labelText: this.translate('default', 'fields', 'ImportConfiguratorItem')
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }
            });
        },

    })
);