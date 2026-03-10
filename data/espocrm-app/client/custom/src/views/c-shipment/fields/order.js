define('custom:views/c-shipment/fields/order', ['views/fields/link'], function (Dep) {

  return Dep.extend({

    setup: function () {
      Dep.prototype.setup.call(this);

      this.listenTo(this.model, 'change:orderId', () => {
        const orderId = this.model.get('orderId');

        if (orderId && !this.model.get('accountId')) {
          this.ajaxGetRequest('COrder/' + orderId).then((order) => {
            if (order.accountId) {
              this.model.set('accountId', order.accountId);
              this.model.set('accountName', order.accountName);
            }
          });
        }
      });
    },

    getSelectFilters: function () {
      const accountId = this.model.get('accountId');

      if (accountId) {
        return {
          account: {
            type: 'equals',
            field: 'accountId',
            value: accountId
          }
        };
      }
    }
  });
});
