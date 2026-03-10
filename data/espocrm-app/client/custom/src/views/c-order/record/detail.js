define('custom:views/c-order/record/detail', ['views/record/detail'], function (Dep) {

  return Dep.extend({

    setup: function () {
      Dep.prototype.setup.call(this);

      this.listenTo(this.model, 'after:relate after:unrelate', () => {
        this.refreshPage();
      });

      this.setupPanelButtons();
    },

    setupPanelButtons: function () {
      this.addButton({
        name: 'printPdf',
        label: 'Print PDF',
        style: 'default',
        action: 'printPdf',
        iconClass: 'fas fa-file-pdf'
      }, true);

      this.addButton({
        name: 'addProducts',
        label: 'Add Products',
        style: 'primary',
        action: 'addProducts'
      }, true);
    },

    actionAddProducts: function () {
      this.createView('dialog', 'custom:views/c-order/modals/add-products', {
        scope: 'CProduct',
        multiple: true,
        createButton: false,
        orderId: this.model.id,
        orderModel: this.model
      }, (view) => {
        view.render();

        this.listenToOnce(view, 'select', (selectedProducts) => {
          if (!selectedProducts || selectedProducts.length === 0) {
            return;
          }

          this.addProductsToOrder(selectedProducts).then(() => {
            this.refreshPage();
          });
        });
      });
    },

    addProductsToOrder: function (products) {
      Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

      const promises = products.map(product => {
        const data = {
          orderId: this.model.id,
          productId: product.id,
          quantity: product.quantity || 1,
          unitPriceCny: product.unitPriceCny || product.get('unitPriceCny')
        };

        return Espo.Ajax.postRequest('COrderItem', data);
      });

      return Promise.all(promises)
        .then(() => {
          Espo.Ui.success(this.translate('Products added successfully', 'labels', 'COrder'));
        })
        .catch((xhr) => {
          let message = this.translate('Error');
          if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
          }
          Espo.Ui.error(message);
          throw xhr;
        });
    },

    refreshPage: function () {
      this.model.fetch();
      this.refreshOrderItemsPanel();
    },

    refreshOrderItemsPanel: function () {
      const bottomView = this.getView('bottom');
      if (bottomView) {
        const orderItemsView = bottomView.getView('orderItems');
        if (orderItemsView && orderItemsView.collection) {
          orderItemsView.collection.fetch();
        }
      }
    }
  });
});
