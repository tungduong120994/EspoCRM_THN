define('custom:views/c-order/modals/add-products', ['views/modals/select-records'], function (Dep) {

  return Dep.extend({

    scope: 'CProduct',

    multiple: true,

    createButton: false,

    searchPanel: false,

    massRelateEnabled: false,

    filtersDisabled: false,

    setup: function () {
      this.boolFilterList = this.options.boolFilterList || [];
      this.primaryFilterName = this.options.primaryFilterName || null;

      Dep.prototype.setup.call(this);

      this.headerText = this.translate('Select Products to Add', 'labels', 'COrder');

      this.buttonList = [
        {
          name: 'add',
          label: this.translate('Add Selected', 'labels', 'COrder'),
          style: 'primary',
          onClick: () => {
            this.addSelectedProducts();
          }
        },
        {
          name: 'cancel',
          label: this.translate('Cancel')
        }
      ];

      this.selectedProductsData = {};
    },

    afterRender: function () {
      Dep.prototype.afterRender.call(this);

      this.listAccountProducts();
    },

    listAccountProducts: function () {
      const listView = this.getView('list');
      const accountId = this.options.orderModel.get('accountId');

      listView.collection.where = [{
        type: 'equals',
        attribute: 'accountId',
        value: accountId
      }];

      listView.collection.fetch();
    },

    addSelectedProducts: function () {
      const selected = this.getSelected();

      if (!selected || selected.length === 0) {
        Espo.Ui.warning(this.translate('No products selected', 'labels', 'COrder'));
        return;
      }

      const productsWithQuantity = selected.map(model => {
        const productId = model.id;
        const quantity = this.selectedProductsData[productId]?.quantity || 1;

        return {
          id: productId,
          name: model.get('name'),
          quantity: quantity,
          unitPriceCny: model.get('unitPriceCny')
        };
      });

      this.trigger('select', productsWithQuantity);
      this.close();
    },

    getSelected: function () {
      const list = [];

      const listView = this.getView('list');
      if (listView) {
        const selected = listView.getSelected();

        if (selected && selected.length) {
          selected.forEach((model) => {
            list.push(model);
          });
        }
      }

      return list;
    },

    data: function () {
      return {
        scope: this.scope
      };
    }
  });
});
