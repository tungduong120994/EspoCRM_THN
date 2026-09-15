define('custom:controllers/inventory', ['controllers/base'], function (Base) {
    return Base.extend({actionIndex: function () { this.main('custom:views/logistics/inventory'); }});
});
