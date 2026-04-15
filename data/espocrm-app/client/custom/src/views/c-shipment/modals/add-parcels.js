define('custom:views/c-shipment/modals/add-parcels', ['views/modals/select-records'], function (Dep) {

  return Dep.extend({

    scope: 'CParcel',

    multiple: true,

    createButton: false,

    searchPanel: false,

    massRelateEnabled: false,

    filtersDisabled: false,

    setup: function () {
      this.boolFilterList = this.options.boolFilterList || [];
      this.primaryFilterName = this.options.primaryFilterName || null;

      Dep.prototype.setup.call(this);

      this.headerText = this.translate('Select Parcels to Add', 'labels', 'CShipment');

      this.buttonList = [
        {
          name: 'add',
          label: this.translate('Add Selected', 'labels', 'CShipment'),
          style: 'primary',
          onClick: () => {
            this.addSelectedParcels();
          }
        },
        {
          name: 'cancel',
          label: this.translate('Cancel')
        }
      ];
    },

    afterRender: function () {
      Dep.prototype.afterRender.call(this);

      this.listAccountParcels();
    },

    listAccountParcels: function () {
      const listView = this.getView('list');
      const accountId = this.options.shipmentModel.get('accountId');

      if (!accountId) {
        Espo.Ui.warning(this.translate('No account linked to this shipment', 'messages', 'CShipment'));
        return;
      }

      // Filtrer les parcels par Account
      listView.collection.where = [{
        type: 'equals',
        attribute: 'accountId',
        value: accountId
      }];

      listView.collection.fetch();
    },

    addSelectedParcels: function () {
      const selected = this.getSelected();

      if (!selected || selected.length === 0) {
        Espo.Ui.warning(this.translate('No parcels selected', 'labels', 'CShipment'));
        return;
      }

      const parcelsData = selected.map(model => {
        return {
          id: model.id,
          name: model.get('name'),
          weight: model.get('weight'),
          volume: model.get('volume'),
          packageCount: model.get('packageCount')
        };
      });

      this.trigger('select', parcelsData);
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
    }
  });
});
