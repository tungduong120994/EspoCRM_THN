define('custom:views/c-order/record/list', ['views/record/list'], function (Dep) {

  return Dep.extend({

    massActionList: ['remove', 'merge', 'massUpdate', 'export'],

    checkAllResultMassActionList: ['remove', 'massUpdate', 'export'],

    setup: function () {
      Dep.prototype.setup.call(this);
    },

    afterRender: function () {
      Dep.prototype.afterRender.call(this);

      // Ajouter le bouton Export by Date Range
      this.addExportButton();
    },

    addExportButton: function () {
      // Trouver le conteneur des boutons header
      const $header = this.$el.find('.list-buttons-container');

      if (!$header.length) {
        return;
      }

      // Vérifier si le bouton existe déjà
      if (this.$el.find('.action-export-date-range').length) {
        return;
      }

      // Créer le bouton
      const $button = $('<button>')
        .addClass('btn btn-default action-export-date-range')
        .attr('type', 'button')
        .css('margin-right', '5px')
        .html('<span class="fas fa-file-export"></span> ' + this.translate('Export by Date Range', 'labels', 'COrder'));

      // Ajouter l'événement click
      $button.on('click', () => {
        this.actionExportByDateRange();
      });

      // Insérer le bouton avant le bouton Create
      $header.prepend($button);
    },

    actionExportByDateRange: function () {
      this.createView('dialog', 'custom:views/c-order/modals/export-date-range', {
        scope: this.scope
      }, (view) => {
        view.render();

        this.listenToOnce(view, 'export', (data) => {
          this.exportByDateRange(data.dateFrom, data.dateTo, data.format);
        });
      });
    },

    exportByDateRange: function (dateFrom, dateTo, format) {
      Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

      // Créer les filtres de date au format EspoCRM
      const where = [];

      if (dateFrom) {
        where.push({
          type: 'on',
          attribute: 'orderDate',
          value: dateFrom,
          dateTime: false
        });
      }

      if (dateTo) {
        where.push({
          type: 'on',
          attribute: 'orderDate',
          value: dateTo,
          dateTime: false
        });
      }

      // Si les deux dates sont présentes, utiliser between
      if (dateFrom && dateTo) {
        where.length = 0; // Vider le tableau
        where.push({
          type: 'between',
          attribute: 'orderDate',
          value: [dateFrom, dateTo],
          dateTime: false
        });
      }

      const searchParams = {
        where: where
      };

      // Utiliser l'API d'export native d'EspoCRM
      Espo.Ajax.postRequest('Export', {
        entityType: this.scope,
        searchParams: searchParams,
        attributeList: null, // Toutes les colonnes
        format: format || 'xlsx'
      }).then((result) => {
        if (result.id) {
          // Télécharger le fichier
          window.location = this.getBasePath() + '?entryPoint=download&id=' + result.id;
          Espo.Ui.success(this.translate('Done'));
        }
      }).catch(() => {
        Espo.Ui.error(this.translate('Error occurred'));
      });
    }
  });
});
