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

                this.listenTo(this.model, 'change:name change:attributeValue change:createIfNotExist', () => {
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

            if (this.model.attributes.measureId) {
                delete this.model.attributes.measureId;
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

            if (type === 'enum' || type === 'multiEnum' || type === 'array') {
                this.params.options = options;
                this.params.translatedOptions = {};
                options.forEach(option => {
                    let label = this.getLanguage().translateOption(option, this.model.get('name'), this.model.get('entity'));
                    if (option === label) {
                        label = this.translate(option, 'labels', this.model.get('entity'));
                    }
                    this.params.translatedOptions[option.toString()] = label;
                });
            }

            if (type === 'unit') {
                let unitsOfMeasure = this.getConfig().get('unitsOfMeasure') || {};
                if (this.model.get('type') === 'Attribute') {
                    this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`, null, {async: false}).then(attribute => {
                        if (attribute.measureId) {
                            this.model.set('measureId', attribute.measureId)
                            let measure = attribute.measureName;
                            if (!this.model.has('defaultUnit') && unitsOfMeasure[measure] && unitsOfMeasure[measure]['unitList'][0]) {
                                this.model.set('defaultUnit', unitsOfMeasure[measure]['unitList'][0]);
                            }
                        }
                    });
                } else {
                    let measure = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measureName`);
                    let measureId = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measureId`);
                    this.model.set('measureId', measureId)
                    if (!this.model.has('defaultUnit') && unitsOfMeasure[measure] && unitsOfMeasure[measure]['unitList'][0]) {
                        this.model.set('defaultUnit', unitsOfMeasure[measure]['unitList'][0]);
                    }
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
            if (this.model.get('attributeValue') === 'unit') {
                if (['rangeInt', 'rangeFloat', 'int', 'float'].includes(type)) {
                    type = 'unit'
                }
            } else if (this.model.get('attributeValue') === 'currency') {
                type = 'currency'
            } else {
                if (type === 'rangeInt') {
                    type = 'int'
                } else if (type === 'rangeFloat') {
                    type = 'float'
                } else if (type === 'currency') {
                    type = 'float'
                }
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

            let viewName = this.getFieldManager().getViewName(type);
            if (type === 'unit') {
                viewName = 'views/admin/field-manager/fields/default-unit'
            } else if (type === 'currency') {
                viewName = 'views/preferences/fields/default-currency'
            }

            if (type === 'extensibleEnum') {
                viewName = 'views/admin/field-manager/fields/link/extensible-enum-default';
                this.model.defs.fields["default"]['extensibleEnumId'] = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.extensibleEnumId`);
            } else if (type === 'extensibleMultiEnum') {
                viewName = 'views/admin/field-manager/fields/linkMultiple/extensible-multi-enum-default';
                this.model.defs.fields["default"]['extensibleEnumId'] = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.extensibleEnumId`);
            }

            if (type === 'unit' && !this.model.get('measureId')) return
            this.createView('default', viewName, {
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
