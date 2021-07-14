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

Espo.define('import:views/import-feed/fields/column-container', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'import:import-feed/fields/column-container/base',
        detailTemplate: 'import:import-feed/fields/column-container/base',
        editTemplate: 'import:import-feed/fields/column-container/base',

        containerViews: {},

        data() {
            return {
                containerViews: this.containerViews
            }
        },

        setup() {
            this.defs = this.options.defs || {};
            this.name = this.options.name || this.defs.name;
            this.params = this.options.params || this.defs.params || {};

            this.createColumnFields();
            this.listenTo(this.model, 'change:name change:attributeId', () => {
                this.createColumnFields();
                this.reRender();
            });
        },

        createColumnFields() {
            this.containerViews = {};
            this.containerViews['column'] = true;
            this.createView('column', 'import:views/import-feed/fields/column', {
                model: this.model,
                el: `${this.options.el} .field[data-name="column"]`,
                name: 'column',
                defs: this.defs,
                params: this.params,
                inlineEditDisabled: true,
                mode: this.mode
            });
        },

        initTooltip(name) {
            $a = this.$el.find('.single-column-info');
            $a.popover({
                placement: 'bottom',
                container: 'body',
                content: this.translate(name, 'tooltips', 'ImportFeed').replace(/(\r\n|\n|\r)/gm, '<br>'),
                trigger: 'click',
                html: true
            }).on('shown.bs.popover', function () {
                $('body').one('click', function () {
                    $a.popover('hide');
                });
            });
        },

        fetch() {
            let data = {};
            $.each(this.nestedViews, (name, view) => _.extend(data, view.fetch()));
            return data;
        },

        validate() {
            let validate = false;
            let view = this.getView('column');
            if (view) {
                validate = view.validate();
            }
            return validate;
        },

        setMode(mode) {
            Dep.prototype.setMode.call(this, mode);

            $.each(this.nestedViews, (name, view) => view.setMode(mode));
        }

    })
);