define('custom:views/c-order/fields/service-fee-percent', ['views/fields/float'], function (Float) {
    return Float.extend({
        data: function () {
            const data = Float.prototype.data.call(this);
            if (this.model.get(this.name) == null) {
                data.value = Number(this.model.get('serviceFeeVndPercent') || 0) * 100;
                data.valueIsSet = true;
                data.isNotEmpty = true;
            }
            return data;
        },
        getValueForDisplay: function () {
            const value = this.model.get(this.name);
            return this.formatNumber(value == null ? Number(this.model.get('serviceFeeVndPercent') || 0) * 100 : value);
        }
    });
});
