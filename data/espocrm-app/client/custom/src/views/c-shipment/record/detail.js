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
      for (const item of [
        {name: 'confirmShipment', label: 'Xác nhận PXK'},
        {name: 'cancelShipment', label: 'Hủy PXK'},
        {name: 'returnShipment', label: 'Hoàn hàng'},
        {name: 'shipmentPayments', label: 'Thu tiền và công nợ'}
      ]) {
        this.addButton({...item, action: item.name, style: 'default'}, true);
      }
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
      if (['confirmed', 'cancelled'].includes(this.model.get('workflowStatus'))) {
        Espo.Ui.error('Phiếu đã chốt. Hãy hủy phiếu trước khi thay danh sách kiện.');
        return;
      }
      this.createView('addParcelsModal', 'custom:views/c-shipment/modals/add-parcels', {
        shipmentModel: this.model
      }, (view) => {
        view.render();

        this.listenToOnce(view, 'select', (parcelsData) => {
          this.linkParcelsToShipment(parcelsData);
        });
      });
    },

    linkParcelsToShipment: async function (parcelsData) {
      if (!parcelsData || parcelsData.length === 0) {
        return;
      }

      Espo.Ui.notify(this.translate('Linking...'));

      try {
        // Each save reprices the whole lot. Complete it before linking the next
        // parcel so concurrent requests do not overwrite each other's totals.
        for (const parcel of parcelsData) {
          await Espo.Ajax.putRequest('CParcel/' + parcel.id, {
            shipmentId: this.model.id
          });
        }
        Espo.Ui.success(this.translate('Linked'));
      } catch (e) {
        Espo.Ui.error(this.translate('Error occurred'));
      } finally {
        this.model.fetch();
        this.refreshParcelsPanel();
      }
    },

    refreshParcelsPanel: function () {
      const bottomView = this.getView('bottom');
      if (bottomView) {
        const parcelsView = bottomView.getView('parcels');
        if (parcelsView && parcelsView.collection) {
          parcelsView.collection.fetch();
        }
      }
    },

    actionConfirmShipment: function () { this.openShipmentAction('confirm'); },
    actionCancelShipment: function () { this.openShipmentAction('cancel'); },
    actionReturnShipment: function () { this.openShipmentAction('return'); },
    actionShipmentPayments: function () { this.getRouter().navigate('#CFinance', {trigger: true}); },
    openShipmentAction: function (operation) {
      if (!this.model.get('accountId')) { Espo.Ui.error('Chọn khách hàng và lưu phiếu trước.'); return; }
      this.createView('shipmentAction', 'custom:views/logistics/shipment-action', {operation, shipment: this.model}, view => {
        view.render();
        this.listenToOnce(view, 'done', () => { this.model.fetch(); this.refreshParcelsPanel(); });
      });
    }
  });
});
