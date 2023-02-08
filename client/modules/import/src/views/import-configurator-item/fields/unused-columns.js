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

Espo.define('import:views/import-configurator-item/fields/source-fields', 'views/fields/array', function (Dep) {
    return Dep.extend({

        detailTemplate: 'import:import-configurator-item/fields/source-fields/detail',

        getValueForDisplay() {
            return this.selected.sort((a, b) => a.localeCompare(b));
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' && ['JSON', 'XML'].includes(this.getFormat())) {
                let html = '';
                (this.model.get(this.name) || []).forEach(column => {
                    let parts = column.split('.');
                    let last = parts.pop();
                    html += '<span style="width:100%;float:left"><span style="color: #bbb">' + parts.join('.') + '</span>.' + last + '</span>';
                });

                this.$el.html(html);
            }
        },

        getFormat() {
            if (
                this.getParentView()
                && this.getParentView().getParentView()
                && this.getParentView().getParentView().getParentView()
                && this.getParentView().getParentView().getParentView().getParentView()
            ) {
                let view = this.getParentView().getParentView().getParentView().getParentView();
                if (view.model) {
                    return view.model.get('format');
                }
                if (view.getParentView() && view.getParentView().model) {
                    return view.getParentView().model.get('format');
                }
            }

            return null;
        },

    })
});
