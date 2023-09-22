/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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

            this.listenTo(this.model, 'change:attributeData', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.hide();
            if (this.model.get('type') === 'Attribute' && this.model.get('attributeData')) {
                if (this.model.get('attributeData').isMultilang) {
                    this.show();
                }
            }
        },

    })
);