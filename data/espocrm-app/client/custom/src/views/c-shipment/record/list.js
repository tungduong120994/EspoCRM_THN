define('custom:views/c-shipment/record/list', ['views/record/list'], function (Dep) {

    return Dep.extend({
        // template: 'c-shipment/record/list',

        massActionList: ['remove', 'merge', 'massUpdate', 'export'],
        checkAllResultMassActionList: ['remove', 'massUpdate', 'export'],

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        // Giữ lại các hàm cũ của bạn bên dưới
        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.addExportButton();
        },

        addExportButton: function () {
            const $header = this.$el.find('.list-buttons-container');
            if (!$header.length || this.$el.find('.action-export-date-range').length) return;

            const $button = $('<button>')
                .addClass('btn btn-default action-export-date-range')
                .attr('type', 'button')
                .css('margin-right', '5px')
                .html('<span class="fas fa-file-export"></span> ' + this.translate('Export by Date Range', 'labels', 'CShipment'));

            $button.on('click', () => { this.actionExportByDateRange(); });
            $header.prepend($button);
        },

        actionExportByDateRange: function () {
            this.createView('dialog', 'custom:views/c-shipment/modals/export-date-range', {
                scope: this.scope
            }, (view) => {
                view.render();
                this.listenToOnce(view, 'export', (data) => {
                    this.exportByDateRange(data.dateFrom, data.dateTo, data.format);
                });
            });
        },

        exportByDateRange: function (dateFrom, dateTo, format) {
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
            const where = [];
            if (dateFrom && dateTo) {
                where.push({ type: 'between', attribute: 'createdAt', value: [dateFrom, dateTo], dateTime: true });
            } else if (dateFrom) {
                where.push({ type: 'on', attribute: 'createdAt', value: dateFrom, dateTime: true });
            } else if (dateTo) {
                where.push({ type: 'on', attribute: 'createdAt', value: dateTo, dateTime: true });
            }

            Espo.Ajax.postRequest('Export', {
                entityType: this.scope,
                searchParams: { where: where },
                attributeList: null,
                format: format || 'xlsx'
            }).then((result) => {
                if (result.id) {
                    window.location = this.getBasePath() + '?entryPoint=download&id=' + result.id;
                    Espo.Ui.success(this.translate('Done'));
                }
            }).catch(() => {
                Espo.Ui.error(this.translate('Error occurred'));
            });
        }
    });
});
