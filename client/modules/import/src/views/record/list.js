

/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore UG (haftungsbeschränkt).
 *
 * This Software is the property of AtroCore UG (haftungsbeschränkt) and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

Espo.define('import:views/record/list', 'views/record/list',
    Dep => Dep.extend({

        checkboxesChecked: [],

        selectAll: false,

        collectionFetchInterval: null,

        setup() {
            this.rowActionsView = this.options.rowActionsView || this.getMetadata().get(['clientDefs', this.options.scope || this.collection.name || null, 'rowActionsView']) || this.rowActionsView;

            Dep.prototype.setup.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (!this.getParentView().dialog) {
                this.setCollectionFetchInterval();
                if (this.selectAll) {
                    this.$el.find('.select-all').trigger('click');
                } else if (this.checkboxesChecked) {
                    this.$el.find('.record-checkbox').each(function (key, value) {
                        if (this.checkboxesChecked.includes(value.getAttribute('data-id'))) {
                            this.$el.find(value).trigger('click')
                        }
                    }.bind(this));
                }
            }
        },

        setCollectionFetchInterval() {
            let collectionFetchInterval = this.getMetadata().get(['clientDefs', this.scope, 'collectionFetchInterval']);
            if (collectionFetchInterval && !this.collectionFetchInterval) {
                this.collectionFetchInterval = window.setInterval(() => {
                    if (!$('.dialog.modal').length) {
                        this.checkboxesChecked = Espo.Utils.clone(this.checkedList);
                        this.selectAll = false;
                        if (this.$el.find('.select-all')[0].checked) {
                            this.selectAll = true;
                        }
                        this.collection.fetch();
                    }
                }, collectionFetchInterval);
                this.listenToOnce(this.getRouter(), 'routed', () => {
                    this.collectionFetchInterval && window.clearInterval(this.collectionFetchInterval);
                    this.collectionFetchInterval = null;
                });
            }
        }

    })
);