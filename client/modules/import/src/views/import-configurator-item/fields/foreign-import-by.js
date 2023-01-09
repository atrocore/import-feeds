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

Espo.define('import:views/import-configurator-item/fields/foreign-import-by', 'views/fields/multi-enum',
    Dep => Dep.extend({

        allowedTypes: ['bool', 'enum', 'varchar', 'email', 'float', 'int', 'text', 'wysiwyg'],

        setup() {
            Dep.prototype.setup.call(this);

            this.validations = Espo.Utils.clone(this.validations);
            if (!this.validations.includes('columns')) {
                this.validations.push('columns');
            }

            this.prepareOptions();
            this.listenTo(this.model, 'change:name', () => {
                this.prepareOptions();
            });

            this.listenTo(this.model, 'change:createIfNotExist', () => {
                this.model.set(this.name, null);
            });

            this.listenTo(this.model, 'change:importBy', () => {
                const importBy = this.model.get('importBy');

                if (importBy && this.model.get(this.name)) {
                    this.model.set(this.name, this.model.get('foreignImportBy').filter(field => !importBy.includes(field)));
                }

                this.prepareOptions();
                this.reRender();
            });
        },

        prepareOptions() {
            if (this.model.get('name')) {
                this.params.options = [];
                this.translatedOptions = {};

                let foreignEntity = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.links.${this.model.get('name')}.entity`);

                if (foreignEntity) {
                    $.each(this.getMetadata().get(`entityDefs.${foreignEntity}.fields`) || {}, (name, data) => {
                        if (data.type
                            && this.allowedTypes.includes(data.type)
                            && !data.disabled
                            && !data.importDisabled) {
                            this.params.options.push(name);
                            this.translatedOptions[name] = this.translate(name, 'fields', foreignEntity);
                        }
                    });
                }
            }
        },

        validateColumns() {
            let validate = false;

            const columns = (this.model.get('foreignColumn') || []).length,
                  fields = (this.model.get(this.name) || []).length;

            if ((columns > 1 && fields !== columns) || (columns === 1 && fields < 1)) {
                this.showValidationMessage(this.translate('wrongForeignFieldsNumber', 'exceptions', 'ImportConfiguratorItem'), this.$el);
                validate = true;
            }

            return validate;
        }
    })
);
