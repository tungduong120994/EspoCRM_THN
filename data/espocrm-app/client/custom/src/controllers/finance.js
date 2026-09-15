define('custom:controllers/finance', ['controllers/base'], function (Base) {
    return Base.extend({actionIndex: function () { this.main('custom:views/logistics/finance'); }});
});
