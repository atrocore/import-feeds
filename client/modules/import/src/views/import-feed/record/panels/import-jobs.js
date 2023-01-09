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

Espo.define('import:views/import-feed/record/panels/import-jobs', 'views/record/panels/relationship',
    Dep => Dep.extend({

        refreshIntervalGap: 5000,

        refreshInterval: null,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenToOnce(this, 'after:render', () => {
                if (this.collection) {
                    this.refreshInterval = window.setInterval(() => {
                        this.actionRefresh();
                    }, this.refreshIntervalGap);
                }
            });

            this.listenToOnce(this, 'remove', () => {
                if (this.refreshInterval) {
                    window.clearInterval(this.refreshInterval);
                }
            });

            this.listenTo(this.model, 'importRun', () => {
                this.actionRefresh();
            });
        },

        actionCancelImportJob(data) {
            let model = this.collection.get(data.id);

            this.notify('Saving...');
            model.set('state', 'Canceled');
            model.save().then(() => {
                this.notify('Saved', 'success');
            });
        },

        actionRefresh() {
            if ($('.panel-body[data-name="importJobs"] .list-row-buttons.open').length === 0) {
                Dep.prototype.actionRefresh.call(this);
            }
        },

    })
);
