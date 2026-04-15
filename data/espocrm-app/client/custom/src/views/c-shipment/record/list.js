define('custom:views/c-shipment/record/list', ['views/record/list'], function (Dep) {

    return Dep.extend({
        // template: 'c-shipment/record/list',

        massActionList: ['remove', 'merge', 'massUpdate', 'export'],
        checkAllResultMassActionList: ['remove', 'massUpdate', 'export'],

        setup: function () {
            Dep.prototype.setup.call(this);

            // Bật một thông báo để chắc chắn file này đang chạy phiên bản mới nhất
            // console.log("--- List View Custom Loaded Successfully ---");

            // CAN THIỆP VÀO COLLECTION FETCH
            // Đây là cách duy nhất để sửa URL API khi các hàm View bị bỏ qua
            var self = this;
            var originalFetch = this.collection.fetch.bind(this.collection);

            this.collection.fetch = function (options) {
                // console.log("--- Đang chặn đứng request API ---");
                
                if (this.where && this.where.length > 0) {
                    // console.log("Dữ liệu lọc gốc:", JSON.stringify(this.where));

                    // Biến đổi cấu trúc
                    this.where = this.where.map(function (item) {
                        if (item.type === 'textFilter') {
                            // console.log("Phát hiện textFilter:", item.value);
                            return {
                                type: 'and',
                                value: [
                                    {
                                        type: 'contains',
                                        attribute: 'name',
                                        value: item.value
                                    }
                                ]
                            };
                        }
                        return item;
                    });

                    // console.log("Dữ liệu lọc đã biến đổi:", JSON.stringify(this.where));
                }

                return originalFetch(options);
            };
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