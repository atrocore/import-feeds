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

Espo.define('import:views/import-configurator-item/fields/locale', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            this.params.options = ['main'];
            this.translatedOptions = {"main": this.translate('main', 'labels', 'ImportConfiguratorItem')};

            (this.getConfig().get('inputLanguageList') || []).forEach(locale => {
                this.params.options.push(locale);
                this.translatedOptions[locale] = this.getLanguage().translateOption(locale, 'language', 'Global');
            });

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:attributeId', () => {
                this.model.set('attributeIsMultilang', false);
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.hide();
            if (this.model.get('type') === 'Attribute' && this.model.get('attributeId')) {
                if (this.model.get('attributeIsMultilang')) {
                    this.show();
                } else {
                    this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).then(attribute => {
                        if (attribute.isMultilang) {
                            this.show();
                        }
                    });
                }
            }
        },

    })
);