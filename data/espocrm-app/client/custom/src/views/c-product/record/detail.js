define('custom:views/c-product/record/detail', ['views/record/detail'], function (Dep) {

  return Dep.extend({

    setup: function () {
      Dep.prototype.setup.call(this);
    },

    actionAddToOrder: function () {
      this.createView('dialog', 'custom:views/c-product/modals/create-order', {
        productId: this.model.id,
        productModel: this.model
      }, (view) => {
        view.render();

        this.listenToOnce(view, 'create', (orderData) => {
          if (!orderData) {
            return;
          }

          this.createOrderWithProduct(orderData);
        });
      });
    },

    createOrderWithProduct: function (orderData) {
      Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

      // First, create the order
      const accountId = this.model.get('accountId');

      const orderPayload = {
        accountId: accountId,
        orderDate: orderData.orderDate,
        exchangeRate: orderData.exchangeRate
      };

      Espo.Ajax.postRequest('COrder', orderPayload)
        .then((createdOrder) => {
          // Then add the product to the order
          const orderItemData = {
            orderId: createdOrder.id,
            productId: this.model.id,
            quantity: orderData.quantity || 1,
            unitPriceCny: this.model.get('unitPriceCny')
          };

          return Espo.Ajax.postRequest('COrderItem', orderItemData)
            .then(() => {
              Espo.Ui.success(this.translate('Order created successfully', 'labels', 'CProduct'));

              // Navigate to the created order
              this.getRouter().navigate(`#COrder/view/${createdOrder.id}`, { trigger: true });
            });
        })
        .catch((xhr) => {
          let message = this.translate('Error');
          if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
          }
          Espo.Ui.error(message);
        });
    }
  });
});
