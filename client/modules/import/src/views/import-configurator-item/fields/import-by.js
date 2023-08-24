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

Espo.define('import:views/import-configurator-item/fields/import-by', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.validations = Espo.Utils.clone(this.validations);
            if (!this.validations.includes('columns')) {
                this.validations.push('columns');
            }

            this.prepareImportByOptions();
            this.listenTo(this.model, 'change:name change:type change:attributeId change:attributeValue', () => {
                this.model.set('importBy', null);
                this.prepareImportByOptions(() => {
                    this.reRender();
                });
            });
            this.listenTo(this.model, 'change:defaultId change:defaultIds', () => {
                this.reRender();
            });
        },

        isRequired: function () {
            return this.params.options.length > 0 && !this.model.get('defaultId') && !this.model.get('defaultIds');
        },

        prepareImportByOptions(callback) {
            this.params.options = [];
            this.translatedOptions = {};

            let foreignEntity;
            if (this.model.get('type') === 'Field') {
                foreignEntity = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.entity`) || this.getMetadata().get(`entityDefs.${this.model.get('entity')}.links.${this.model.get('name')}.entity`);
                if (this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.extensibleEnumId`)) {
                    foreignEntity = 'ExtensibleEnumOption';
                } else if (this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`) === 'asset') {
                    foreignEntity = 'Asset';
                }
            } else {
                if (this.model.get('attributeId')) {
                    let attribute = this.getAttribute(this.model.get('attributeId'));
                    if (['extensibleEnum', 'extensibleMultiEnum'].includes(attribute.type)) {
                        foreignEntity = 'ExtensibleEnumOption';
                    }
                    if (attribute.measureId && this.model.get('attributeValue') === 'valueUnitId') {
                        foreignEntity = 'Unit';
                    }
                }
            }

            /**
             * For Main Image
             */
            if (this.model.get('name') === 'mainImage' || ['Product', 'Category'].includes(this.model.get('entity')) && this.model.get('name') === 'image') {
                foreignEntity = 'Asset';
            }

            if (foreignEntity) {
                this.params.options.push('id');
                this.translatedOptions['id'] = this.translate('id', 'fields', 'Global');

                $.each(this.getMetadata().get(`entityDefs.${foreignEntity}.fields`) || {}, (name, data) => {
                    if (
                        data.type
                        && ['bool', 'enum', 'varchar', 'email', 'float', 'int', 'text', 'wysiwyg'].includes(data.type)
                        && !data.disabled
                        && !data.importDisabled
                    ) {
                        this.params.options.push(name);
                        this.translatedOptions[name] = this.translate(name, 'fields', foreignEntity);
                    }
                });
            }

            if (callback) {
                callback();
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.params.options.length) {
                this.show();
            } else {
                this.hide();
            }
        },

        validateColumns() {
            let validate = false;

            const columns = (this.model.get('column') || []).length,
                fields = (this.model.get(this.name) || []).length;

            if ((columns > 1 && fields !== columns) || (columns === 1 && fields < 1)) {
                this.showValidationMessage(this.translate('wrongFieldsNumber', 'exceptions', 'ImportConfiguratorItem'), this.$el);
                validate = true;
            }

            return validate;
        },

        getAttribute(attributeId) {
            let key = `attribute_${attributeId}`;
            if (!Espo[key]) {
                Espo[key] = null;
                this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`, null, {async: false}).success(attr => {
                    Espo[key] = attr;
                });
            }

            return Espo[key];
        },

    })
);