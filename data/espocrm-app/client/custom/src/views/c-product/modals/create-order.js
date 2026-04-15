define('custom:views/c-product/modals/create-order', ['views/modal'], function (Dep) {

  return Dep.extend({

    template: 'custom:c-product/modals/create-order',

    className: 'dialog dialog-record',

    backdrop: true,

    setup: function () {
      this.buttonList = [
        {
          name: 'create',
          label: this.translate('Create Order', 'labels', 'CProduct'),
          style: 'primary',
          onClick: () => {
            this.createOrder();
          }
        },
        {
          name: 'cancel',
          label: this.translate('Cancel')
        }
      ];

      this.headerText = this.translate('Create Order', 'labels', 'CProduct');

      this.quantity = 1;

      // Wait for models to be created before creating fields
      this.wait(true);

      Promise.all([
        new Promise(resolve => {
          this.getModelFactory().create('COrder', (model) => {
            this.orderModel = model;
            resolve();
          });
        }),
        new Promise(resolve => {
          this.getModelFactory().create('COrderItem', (model) => {
            this.orderItemModel = model;
            resolve();
          });
        })
      ]).then(() => {
        this.createFields();
        this.wait(false);
      });
    },

    createFields: function () {
      this.createOrderDateField();
      this.createExchangeRateField();
      this.createQuantityField();
    },

    createOrderDateField: function () {
      this.createView('orderDate', 'views/fields/date', {
        model: this.orderModel,
        mode: 'edit',
        el: this.getSelector() + ' .field[data-name="orderDate"]',
        defs: {
          name: 'orderDate'
        },
        inlineEditDisabled: true
      }, (view) => {
        // Set default to today
        const today = new Date();
        const dateString = today.toISOString().split('T')[0];
        view.model.set('orderDate', dateString);
      });
    },

    createExchangeRateField: function () {
      this.createView('exchangeRate', 'views/fields/float', {
        model: this.orderModel,
        mode: 'edit',
        el: this.getSelector() + ' .field[data-name="exchangeRate"]',
        defs: {
          name: 'exchangeRate'
        },
        inlineEditDisabled: true
      }, (view) => {
        // Set default exchange rate
        view.model.set('exchangeRate', 3500);
      });
    },

    createQuantityField: function () {
      this.createView('quantity', 'views/fields/int', {
        model: this.orderItemModel,
        mode: 'edit',
        el: this.getSelector() + ' .field[data-name="quantity"]',
        defs: {
          name: 'quantity'
        },
        inlineEditDisabled: true
      }, (view) => {
        view.model.set('quantity', 1);

        this.listenTo(view.model, 'change:quantity', () => {
          this.quantity = view.model.get('quantity') || 1;
        });
      });
    },

    createOrder: function () {
      const orderDate = this.orderModel.get('orderDate');
      const exchangeRate = this.orderModel.get('exchangeRate');
      const quantity = this.orderItemModel.get('quantity') || 1;

      if (!orderDate) {
        Espo.Ui.warning(this.translate('Order Date is required', 'labels', 'CProduct'));
        return;
      }

      if (!exchangeRate) {
        Espo.Ui.warning(this.translate('Exchange Rate is required', 'labels', 'CProduct'));
        return;
      }

      const orderData = {
        orderDate: orderDate,
        exchangeRate: exchangeRate,
        quantity: quantity
      };

      this.trigger('create', orderData);
      this.close();
    },

    data: function () {
      return {};
    }
  });
});