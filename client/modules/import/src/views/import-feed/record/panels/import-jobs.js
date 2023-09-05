/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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

        actionTryAgainImportJob(data) {
            let model = this.collection.get(data.id);

            this.notify('Saving...');
            model.set('state', 'Pending');
            model.save().then(() => {
                this.notify('Saved', 'success');
            });
        },

    })
);
