/*
 * Export Feeds
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

Espo.define('import:views/import-configurator-item/fields/attribute-value', 'views/fields/enum', Dep => Dep.extend({

    setup() {
        Dep.prototype.setup.call(this);

        this.listenTo(this.model, 'change:name change:type change:attributeId', () => {
            this.setupOptions();
            this.model.set('attributeValue', this.params.options[0] ?? null);
            this.reRender();
        });
    },

    afterRender() {
        Dep.prototype.afterRender.call(this);

        if (this.mode !== 'list') {
            this.checkFieldVisibility();
        }
    },

    checkFieldVisibility() {
        if (this.isRequired()) {
            this.show();
        } else {
            this.hide();
        }
    },

    setupOptions() {
        this.params.options = [];
        this.translatedOptions = {};

        if (this.isRequired()) {
            const type = this.getType();
            this.params.options = ['value'];
            if (['rangeFloat', 'rangeInt'].includes(type)) {
                this.params.options = ['valueFrom', 'valueTo']
                if (this.hasUnit()) {
                    this.params.options.push('valueUnit')
                }
            } else if (['float', 'int'].includes(type)) {
                if (this.hasUnit()) {
                    this.params.options.push('valueUnit')
                }
            }

            this.params.options.forEach(option => {
                this.translatedOptions[option] = this.getLanguage().translateOption(option, 'attributeValue', 'ImportConfiguratorItem');
            });
        }
    },

    isRequired() {
        return this.model.get('type') === 'Attribute' && this.model.get('attributeId');
    },

    getType() {
        if (this.model.get('attributeId')) {
            return this.getAttribute(this.model.get('attributeId')).type;
        }

        return 'varchar';
    },

    hasUnit() {
        if (this.model.get('attributeId')) {
            const attribute = this.getAttribute(this.model.get('attributeId'));
            if (attribute.measureId) {
                return true
            }
        }

        return false
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

}));