define('custom:views/c-shipment/record/detail', ['views/record/detail'], function (Dep) {

  return Dep.extend({

    setup: function () {
      Dep.prototype.setup.call(this);

      this.listenTo(this.model, 'after:save', () => {
        this.model.fetch();
        this.refreshParcelsPanel();
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
        name: 'addParcels',
        label: 'Add Parcels',
        style: 'primary',
        action: 'addParcels'
      }, true);
    },

    actionAddParcels: function () {
      this.createView('addParcelsModal', 'custom:views/c-shipment/modals/add-parcels', {
        shipmentModel: this.model
      }, (view) => {
        view.render();

        this.listenToOnce(view, 'select', (parcelsData) => {
          this.linkParcelsToShipment(parcelsData);
        });
      });
    },

    linkParcelsToShipment: function (parcelsData) {
      if (!parcelsData || parcelsData.length === 0) {
        return;
      }

      Espo.Ui.notify(this.translate('Linking...'));

      const promises = parcelsData.map(parcel => {
        return Espo.Ajax.putRequest('CParcel/' + parcel.id, {
          shipmentId: this.model.id
        });
      });

      Promise.all(promises).then(() => {
        Espo.Ui.success(this.translate('Linked'));
        this.model.fetch();
        this.refreshParcelsPanel();
      }).catch(() => {
        Espo.Ui.error(this.translate('Error occurred'));
      });
    },

    refreshParcelsPanel: function () {
      const bottomView = this.getView('bottom');
      if (bottomView) {
        const parcelsView = bottomView.getView('parcels');
        if (parcelsView && parcelsView.collection) {
          parcelsView.collection.fetch();
        }
      }
    }
  });
});
