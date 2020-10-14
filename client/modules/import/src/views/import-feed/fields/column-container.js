

Espo.define('import:views/import-feed/fields/column-container', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'import:import-feed/fields/column-container/base',
        detailTemplate: 'import:import-feed/fields/column-container/base',
        editTemplate: 'import:import-feed/fields/column-container/base',

        complexTypes: ['currency', 'unit'],

        columns: [],

        data() {
            return {
                columns: this.columns
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
            this.clearColumnData();
            this.updateColumnData();

            this.columns.forEach(column => {
                this.createView(column.name, 'import:views/import-feed/fields/column', {
                    model: this.model,
                    el: `${this.options.el} .field[data-name="${column.name}"]`,
                    name: column.name,
                    defs: this.defs,
                    params: this.params,
                    inlineEditDisabled: true,
                    mode: this.mode
                }, view => view.render());
            });
        },

        clearColumnData() {
            this.columns.forEach(name => this.clearView(name));
            this.columns = [
                {
                    name: 'column',
                    label: ''
                }
            ];
        },

        updateColumnData() {
            const fieldDefs = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name')]) || {};
            const type = this.model.get('type') || this.model.getFieldType('default') || fieldDefs.type || 'base';

            if (this.complexTypes.includes(type)) {
                this.getFieldManager().getActualAttributes(type, 'column').forEach(name => {
                    const existed = this.columns.some(item => item.name === name);
                    if (!existed) {
                        const fieldPart = name.replace('column', '').toLowerCase();
                        this.columns.push({
                            name: name,
                            label: (this.getLanguage().data['FieldManager']["fieldParts"][type] || {})[fieldPart] || ''
                        });
                    }
                });
            }
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