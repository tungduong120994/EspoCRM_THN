define('custom:views/c-shipment/fields/additional-orders', ['views/fields/multi-enum'], function (MultiEnum) {
    return MultiEnum.extend({
        setup: function () {
            MultiEnum.prototype.setup.call(this);
            this.wait(this.loadOrders());
            this.listenTo(this.model, 'change:accountId', async () => { await this.loadOrders(); this.reRender(); });
        },
        loadOrders: async function () {
            const ids = this.model.get(this.name) || [];
            this.params.options = ids.slice(); this.translatedOptions = {};
            if (!this.model.get('accountId')) { return; }
            try {
                const result = await Espo.Ajax.getRequest('CLogistics/order-options', {accountId: this.model.get('accountId')});
                for (const row of result.list) {
                    if (!this.params.options.includes(row.id)) { this.params.options.push(row.id); }
                    this.translatedOptions[row.id] = row.name;
                }
            } catch (e) { Espo.Ui.error('Không tải được các đơn của khách hàng.'); }
        }
    });
});
