define('custom:views/c-order/modals/export-date-range', ['views/modal'], function (Dep) {

  return Dep.extend({

    templateContent: `
            <div class="record">
                <div class="record-grid">
                    <div class="left">
                        <div class="panel panel-default">
                            <div class="panel-body">
                                <div class="cell form-group">
                                    <label class="control-label">
                                        {{translate 'Export from Date' category='labels' scope='COrder'}}
                                    </label>
                                    <div class="field">
                                        <input type="date" class="form-control" name="dateFrom" data-name="dateFrom">
                                    </div>
                                </div>
                                <div class="cell form-group">
                                    <label class="control-label">
                                        {{translate 'Export to Date' category='labels' scope='COrder'}}
                                    </label>
                                    <div class="field">
                                        <input type="date" class="form-control" name="dateTo" data-name="dateTo">
                                    </div>
                                </div>
                                <div class="cell form-group">
                                    <label class="control-label">
                                        {{translate 'Format' category='labels' scope='COrder'}}
                                    </label>
                                    <div class="field">
                                        <select class="form-control" name="format" data-name="format">
                                            <option value="csv">CSV</option>
                                            <option value="xlsx" selected>Excel (XLSX)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `,

    backdrop: true,

    setup: function () {
      this.headerText = this.translate('Export Orders by Date Range', 'labels', 'COrder');

      this.buttonList = [
        {
          name: 'export',
          label: 'Export',
          style: 'primary',
          onClick: () => {
            this.export();
          }
        },
        {
          name: 'cancel',
          label: 'Cancel'
        }
      ];
    },

    afterRender: function () {
      Dep.prototype.afterRender.call(this);

      // Définir la date d'aujourd'hui par défaut
      const today = new Date();
      const todayStr = today.toISOString().split('T')[0]; // Format YYYY-MM-DD

      this.$el.find('input[name="dateFrom"]').val(todayStr);
      this.$el.find('input[name="dateTo"]').val(todayStr);
    },

    export: function () {
      const dateFrom = this.$el.find('input[name="dateFrom"]').val();
      const dateTo = this.$el.find('input[name="dateTo"]').val();
      const format = this.$el.find('select[name="format"]').val();

      if (!dateFrom && !dateTo) {
        Espo.Ui.warning(this.translate('Please select at least one date', 'messages', 'COrder'));
        return;
      }

      if (dateFrom && dateTo && dateFrom > dateTo) {
        Espo.Ui.error(this.translate('Start date must be before end date', 'messages', 'COrder'));
        return;
      }

      this.trigger('export', {
        dateFrom: dateFrom,
        dateTo: dateTo,
        format: format || 'xlsx'
      });

      this.close();
    }
  });
});
