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

Espo.define('import:views/dashlets/data-sync-errors-import', 'views/dashlets/abstract/base',
    Dep => Dep.extend({

        _template: '<div class="list-container">{{{list}}}</div>',

        collectionUrl: 'Dashlet/DataSyncErrorsImport',

        actionRefresh: function () {
            this.collection.fetch();
        },

        afterRender: function () {
            this.getCollectionFactory().create('DataSyncErrorsImportDashlet', function (collection) {
                this.collection = collection;

                collection.url = this.collectionUrl;
                collection.maxSize = this.getOption('displayRecords');

                this.listenToOnce(collection, 'sync', function () {
                    this.createView('list', 'views/record/list', {
                        el: this.getSelector() + ' > .list-container',
                        collection: collection,
                        rowActionsDisabled: true,
                        checkboxes: false,
                        listLayout: [
                            {
                                name: 'name',
                                width: '60'
                            },
                            {
                                name: 'feeds',
                                width: '20',
                                view: "import:views/dashlets/fields/link-feeds"
                            },
                            {
                                name: 'jobs',
                                width: '20',
                                view: "import:views/dashlets/fields/link-jobs"
                            }
                        ],
                    }, view => {
                        view.listenTo(view, 'after:render', () => {
                            let totalJobs = 0;
                            let totalFeeds = 0;
                            collection.each(model => {
                                totalFeeds += model.get('feeds');
                                totalJobs += model.get('jobs');
                            });
                            view.$el.find('table.table tbody').append(
                                `<tr data-id="total" class="list-row">
                                    <td class="cell" data-name="name" width="60%"><b>${this.translate('Total', 'labels', 'Global')}</b></td>
                                    <td class="cell" data-name="feeds" width="20%"><b>${totalFeeds}</b></td>
                                    <td class="cell" data-name="jobs" width="20%"><b>${totalJobs}</b></td>
                                </tr>'`
                            );
                            $('div[data-name="DataSyncErrorsImport"] .table.full-table thead th:first-child div').css('display', 'none');
                        });

                        view.render();
                    });
                }.bind(this));
                collection.fetch();

            }, this);
        },

    })
);

