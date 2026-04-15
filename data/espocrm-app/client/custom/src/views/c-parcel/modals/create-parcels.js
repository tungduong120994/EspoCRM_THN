define('custom:views/c-parcel/modals/create-parcels', ['views/modal'], function (Dep) {

  return Dep.extend({

    templateContent: `
      <div class="record">
        <div class="parcels-grid-container">
          <div class="cell form-group" style="margin-bottom: 15px;">
            <label class="control-label">
              <span class="label-text">Account</span>
            </label>
            <div class="field" data-name="account"></div>
          </div>

          <div class="button-container" style="margin-bottom: 15px;">
            <button type="button" class="btn btn-default btn-sm" data-action="addRow">
              <span class="fas fa-plus"></span> Add Row
            </button>
            <button type="button" class="btn btn-default btn-sm" data-action="removeRow">
              <span class="fas fa-minus"></span> Remove Last Row
            </button>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered parcels-grid" style="margin-bottom: 0;">
              <thead>
                <tr style="background-color: #f8f9fa;">
                  <th style="width: 50px; text-align: center;">#</th>
                  <th style="width: 25%;">Tracking Code</th>
                  <th style="width: 20%;">Weight (kg)</th>
                  <th style="width: 20%;">Volume (m³)</th>
                  <th style="width: 20%;">Package Count</th>
                  <th style="width: 40px; text-align: center;"></th>
                </tr>
              </thead>
              <tbody class="parcels-tbody">
                <!-- Rows will be added here dynamically -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
      
      <style>
        .parcels-grid-container {
          padding: 20px;
        }
        
        .parcels-grid {
          border-collapse: collapse;
          width: 100%;
        }
        
        .parcels-grid th {
          font-weight: 600;
          padding: 10px 8px;
          border: 1px solid #ddd;
          font-size: 13px;
        }
        
        .parcels-grid td {
          padding: 4px;
          border: 1px solid #ddd;
          vertical-align: middle;
        }
        
        .parcels-grid td.row-number {
          text-align: center;
          background-color: #f8f9fa;
          font-weight: 500;
          color: #666;
        }
        
        .parcels-grid input {
          width: 100%;
          border: 1px solid #ddd;
          padding: 6px 8px;
          font-size: 13px;
          border-radius: 3px;
        }
        
        .parcels-grid input:focus {
          outline: none;
          border-color: #5f9dd8;
          box-shadow: 0 0 0 2px rgba(95, 157, 216, 0.1);
        }
        
        .parcels-grid tbody tr:hover {
          background-color: #f9f9f9;
        }
        
        .button-container {
          display: flex;
          gap: 10px;
        }
        
        .table-responsive {
          max-height: 500px;
          overflow-y: auto;
          border: 1px solid #ddd;
          border-radius: 4px;
        }
        
        .btn-remove-row {
          padding: 4px 6px;
          font-size: 11px;
          line-height: 1;
          border: none;
          background: transparent;
          color: #999;
          cursor: pointer;
          border-radius: 3px;
          transition: all 0.2s;
          display: inline-flex;
          align-items: center;
          justify-content: center;
        }
        
        .btn-remove-row:hover {
          background-color: #dc3545;
          color: white;
        }
        
        .btn-remove-row:disabled {
          opacity: 0.3;
          cursor: not-allowed;
        }
        
        .btn-remove-row:disabled:hover {
          background: transparent;
          color: #999;
        }
      </style>
    `,

    backdrop: true,

    className: 'dialog dialog-record',

    events: {
      'click [data-action="addRow"]': function () {
        this.addRow();
      },
      'click [data-action="removeRow"]': function () {
        this.removeLastRow();
      },
      'click .btn-remove-row': function (e) {
        this.removeRow(e);
      },
      'paste input[name^="trackingCode_"]': function (e) {
        this.handleScan(e);
      },
      'keydown input[name^="trackingCode_"]': function (e) {
        if (e.key === 'Enter') {
          this.handleEnter(e);
        }
      }
    },

    data: function () {
      return {
        rows: this.rows || []
      };
    },

    setup: function () {
      this.headerText = this.translate('Create Multiple Parcels', 'labels', 'CParcel');

      // Initialiser avec 3 lignes par défaut
      this.rows = [];
      this.rowCount = 0;
      this.currentFocusedRow = 1;

      this.buttonList = [
        {
          name: 'create',
          label: 'Create',
          style: 'primary',
          onClick: () => {
            this.createParcels();
          }
        },
        {
          name: 'cancel',
          label: 'Cancel'
        }
      ];

      // Créer le modèle de manière asynchrone
      this.wait(
        this.getModelFactory().create('CParcel').then((model) => {
          this.model = model;
        })
      );
    },

    afterRender: function () {
      Dep.prototype.afterRender.call(this);

      // Créer le champ Account
      this.createView('accountField', 'views/fields/link', {
        el: this.getSelector() + ' .field[data-name="account"]',
        model: this.model,
        mode: 'edit',
        defs: {
          name: 'account',
          type: 'link',
          entity: 'Account'
        }
      }, (view) => {
        view.render();
      });

      // Ajouter 1 ligne par défaut
      this.addRow();

      // Focus sur le premier champ trackingCode
      setTimeout(() => {
        this.$el.find('input[name="trackingCode_1"]').focus();
      }, 100);
    },

    addRow: function () {
      this.rowCount++;
      const rowNumber = this.rowCount;

      const $tbody = this.$el.find('.parcels-tbody');

      const $row = $(`
        <tr data-row="${rowNumber}">
          <td class="row-number">${rowNumber}</td>
          <td><input type="text" class="form-control" name="trackingCode_${rowNumber}" placeholder="Enter tracking code"></td>
          <td><input type="number" class="form-control" name="weight_${rowNumber}" step="0.01" min="0" placeholder="0.00"></td>
          <td><input type="number" class="form-control" name="volume_${rowNumber}" step="0.001" min="0" placeholder="0.000"></td>
          <td><input type="number" class="form-control" name="packageCount_${rowNumber}" min="1" value="1"></td>
          <td style="text-align: center; padding: 2px; vertical-align: middle;">
            <button type="button" class="btn-remove-row" data-row="${rowNumber}" title="Remove this row">
              <span class="fas fa-times"></span>
            </button>
          </td>
        </tr>
      `);

      $tbody.append($row);
      this.updateRemoveButtonState();
    },

    removeLastRow: function () {
      const $tbody = this.$el.find('.parcels-tbody');
      const $rows = $tbody.find('tr');

      if ($rows.length > 1) {
        $rows.last().remove();
        this.rowCount--;
        this.updateRemoveButtonState();
      }
    },

    updateRemoveButtonState: function () {
      const $removeButton = this.$el.find('[data-action="removeRow"]');
      const $rows = this.$el.find('.parcels-tbody tr');
      const $rowButtons = this.$el.find('.btn-remove-row');

      if ($rows.length <= 1) {
        $removeButton.prop('disabled', true).addClass('disabled');
        $rowButtons.prop('disabled', true);
      } else {
        $removeButton.prop('disabled', false).removeClass('disabled');
        $rowButtons.prop('disabled', false);
      }
    },

    removeRow: function (e) {
      const $button = $(e.currentTarget);
      const rowNumber = $button.data('row');
      const $row = this.$el.find(`tr[data-row="${rowNumber}"]`);
      const $rows = this.$el.find('.parcels-tbody tr');

      // Ne pas supprimer si c'est la dernière ligne
      if ($rows.length <= 1) {
        return;
      }

      $row.remove();
      this.updateRemoveButtonState();

      // Renuméroter les lignes
      this.renumberRows();
    },

    renumberRows: function () {
      const $rows = this.$el.find('.parcels-tbody tr');
      $rows.each((index, row) => {
        $(row).find('.row-number').text(index + 1);
      });
    },

    handleScan: function (e) {
      // Récupérer la valeur scannée
      const $input = $(e.currentTarget);
      const rowNumber = this.getRowNumberFromInput($input);

      // Laisser le paste se faire normalement
      setTimeout(() => {
        const scannedValue = $input.val();

        if (scannedValue) {
          // Passer à la ligne suivante
          this.moveToNextRow(rowNumber);
        }
      }, 10);
    },

    handleEnter: function (e) {
      e.preventDefault();
      const $input = $(e.currentTarget);
      const rowNumber = this.getRowNumberFromInput($input);

      // Passer à la ligne suivante
      this.moveToNextRow(rowNumber);
    },

    getRowNumberFromInput: function ($input) {
      const name = $input.attr('name');
      const match = name.match(/trackingCode_(\d+)/);
      return match ? parseInt(match[1]) : null;
    },

    moveToNextRow: function (currentRow) {
      const nextRow = currentRow + 1;
      const $nextInput = this.$el.find(`input[name="trackingCode_${nextRow}"]`);

      // Si la ligne suivante n'existe pas, en créer une
      if ($nextInput.length === 0) {
        this.addRow();
        // Focus sur la nouvelle ligne
        setTimeout(() => {
          this.$el.find(`input[name="trackingCode_${nextRow}"]`).focus();
        }, 50);
      } else {
        // Focus sur la ligne existante
        $nextInput.focus();
      }

      this.currentFocusedRow = nextRow;
    },

    getRowData: function () {
      const parcels = [];
      const $rows = this.$el.find('.parcels-tbody tr');

      $rows.each((index, row) => {
        const $row = $(row);
        const rowNumber = $row.data('row');

        const trackingCode = $row.find(`input[name="trackingCode_${rowNumber}"]`).val().trim();
        const weight = parseFloat($row.find(`input[name="weight_${rowNumber}"]`).val()) || null;
        const volume = parseFloat($row.find(`input[name="volume_${rowNumber}"]`).val()) || null;
        const packageCount = parseInt($row.find(`input[name="packageCount_${rowNumber}"]`).val()) || 1;

        // N'ajouter que les lignes qui ont un tracking code
        if (trackingCode) {
          parcels.push({
            trackingCode: trackingCode,
            weight: weight,
            volume: volume,
            packageCount: packageCount,
            rowNumber: rowNumber
          });
        }
      });

      return parcels;
    },

    validateParcels: function () {
      // Réinitialiser les erreurs précédentes
      this.$el.find('input[name^="trackingCode_"]').css('border-color', '');

      const $rows = this.$el.find('.parcels-tbody tr');
      let hasError = false;
      let emptyRows = [];

      $rows.each((index, row) => {
        const $row = $(row);
        const rowNumber = $row.data('row');
        const $trackingInput = $row.find(`input[name="trackingCode_${rowNumber}"]`);
        const trackingCode = $trackingInput.val().trim();

        // Vérifier si la ligne a d'autres champs remplis
        const weight = $row.find(`input[name="weight_${rowNumber}"]`).val();
        const volume = $row.find(`input[name="volume_${rowNumber}"]`).val();

        // Si d'autres champs sont remplis mais pas le tracking code
        if ((weight || volume) && !trackingCode) {
          $trackingInput.css('border-color', '#dc3545');
          hasError = true;
          emptyRows.push(index + 1);
        }
      });

      return { valid: !hasError, emptyRows: emptyRows };
    },

    createParcels: function () {
      // Valider d'abord
      const validation = this.validateParcels();

      if (!validation.valid) {
        Espo.Ui.error(this.translate('Tracking Code is required for all parcels', 'messages', 'CParcel'));
        return;
      }

      const parcelsData = this.getRowData();

      if (parcelsData.length === 0) {
        Espo.Ui.warning(this.translate('Please fill at least one row', 'messages', 'CParcel'));
        return;
      }

      // Récupérer l'account sélectionné
      const accountId = this.model.get('accountId');
      const accountName = this.model.get('accountName');

      // Valider que l'account est sélectionné
      if (!accountId) {
        Espo.Ui.error(this.translate('Account is required', 'messages', 'CParcel'));

        // Surligner le champ Account en rouge
        this.$el.find('.field[data-name="account"] input').css('border-color', '#dc3545');

        // Retirer le surlignage après 3 secondes
        setTimeout(() => {
          this.$el.find('.field[data-name="account"] input').css('border-color', '');
        }, 3000);

        return;
      }

      Espo.Ui.notify(this.translate('Creating parcels...', 'messages', 'CParcel'));

      // Créer les parcels via des appels API - un par un
      const promises = parcelsData.map((parcelData, index) => {
        const data = {
          name: parcelData.trackingCode,
          weight: parcelData.weight,
          volume: parcelData.volume,
          packageCount: parcelData.packageCount
        };

        // Ajouter l'account si sélectionné
        if (accountId) {
          data.accountId = accountId;
          data.accountName = accountName;
        }

        return Espo.Ajax.postRequest('CParcel', data);
      });

      Promise.all(promises)
        .then((responses) => {
          Espo.Ui.success(
            this.translate('Created', 'labels', 'Global') + ': ' + parcelsData.length + ' ' +
            this.translate('parcels', 'labels', 'CParcel')
          );
          this.trigger('created', { count: parcelsData.length, parcels: responses });
          this.close();
        })
        .catch((xhr) => {
          let message = this.translate('Error occurred', 'labels', 'Global');
          if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
          }
          Espo.Ui.error(message);
        });
    }
  });
});
