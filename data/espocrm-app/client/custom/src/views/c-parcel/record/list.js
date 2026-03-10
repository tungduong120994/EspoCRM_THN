define('custom:views/c-parcel/record/list', ['views/record/list'], function (Dep) {

  return Dep.extend({

    massActionList: ['remove', 'merge', 'massUpdate', 'export'],

    checkAllResultMassActionList: ['remove', 'massUpdate', 'export'],

    setup: function () {
      Dep.prototype.setup.call(this);
    },

    afterRender: function () {
      Dep.prototype.afterRender.call(this);

      // Ajouter le bouton Create Parcels
      this.addCreateParcelsButton();
    },

    addCreateParcelsButton: function () {
      // Trouver le conteneur des boutons header
      const $header = this.$el.find('.list-buttons-container');

      if (!$header.length) {
        return;
      }

      // Vérifier si le bouton existe déjà
      if (this.$el.find('.action-create-parcels').length) {
        return;
      }

      // Créer le bouton
      const $button = $('<button>')
        .addClass('btn btn-primary action-create-parcels')
        .attr('type', 'button')
        .css('margin-right', '5px')
        .html('<span class="fas fa-boxes"></span> ' + this.translate('Create Parcels', 'labels', 'CParcel'));

      // Ajouter l'événement click
      $button.on('click', () => {
        this.actionCreateParcels();
      });

      // Insérer le bouton avant le bouton Create
      const $createButton = $header.find('[data-action="create"]');
      if ($createButton.length) {
        $createButton.before($button);
      } else {
        $header.prepend($button);
      }
    },

    actionCreateParcels: function () {
      this.createView('dialog', 'custom:views/c-parcel/modals/create-parcels', {
        scope: this.scope
      }, (view) => {
        view.render();

        this.listenToOnce(view, 'created', (data) => {
          this.collection.fetch();
          Espo.Ui.success(this.translate('Parcels created successfully', 'messages', 'CParcel'));
        });
      });
    }
  });
});
