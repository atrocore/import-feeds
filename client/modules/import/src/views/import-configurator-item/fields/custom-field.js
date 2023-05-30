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

Espo.define('import:views/import-configurator-item/fields/custom-field', 'views/fields/enum', Dep => Dep.extend({

    setup() {
        Dep.prototype.setup.call(this);

        this.listenTo(this.model, 'change:name change:type change:attributeId', () => {
            this.setupOptions();
            this.reRender();
            // this.model.set('customField', null);
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
        const type = this.getType()
        if (['rangeFloat', 'rangeInt'].includes(type)) {
            this.params.options = ['valueFrom', 'valueTo', 'unit']
        } else if (type === 'currency') {
            this.params.options = ['value', 'currency']
        } else if (['float', 'int'].includes(type)) {
            this.params.options = ['value', 'unit']
        }

        this.translatedOptions = {
            value: this.translate('value', 'attributeValue', 'ImportConfiguratorItem'),
            valueFrom: this.translate('valueFrom', 'attributeValue', 'ImportConfiguratorItem'),
            valueTo: this.translate('valueTo', 'attributeValue', 'ImportConfiguratorItem'),
            unit: this.translate('unit', 'attributeValue', 'ImportConfiguratorItem'),
            currency: this.translate('currency', 'attributeValue', 'ImportConfiguratorItem'),
        };
    },

    getType() {
        let type = 'varchar';
        if (this.model.get('type') === 'Attribute') {
            if (this.model.get('attributeId')) {
                type = this.getAttribute(this.model.get('attributeId')).type;
            }
        } else if (this.model.get('type') === 'Field') {
            type = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']);
        }
        return type
    },
    isRequired() {
        return ['rangeFloat', 'rangeInt', 'int', 'float'].includes(this.getType()) && (this.params.options || []).length;
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