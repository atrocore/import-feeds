

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

Espo.define('import:views/admin/fields/max-import-job-in-history', 'views/fields/int',
    Dep => Dep.extend({

        editTemplate: 'import:admin/import-settings/fields/max-import-job-in-history/edit',

        data: function () {
            var data = Dep.prototype.data.call(this);

            data.defaultMaxImportJobInHistory = 20;

            return data;
        }
    })
);