define('custom:views/lead/fields/name', ['views/fields/varchar'], function (Varchar) {
    return Varchar.extend({
        setup: function () {
            Varchar.prototype.setup.call(this);
            this.validations = ['required', 'pattern', 'fullNameLength'];
            this.params.pattern = this.model.getFieldParam('lastName', 'pattern');
        },

        fetch: function () {
            const data = Varchar.prototype.fetch.call(this);
            const value = data[this.name];

            // Keep existing split names (and salutation) when only other fields change.
            if (value === (this.model.get(this.name) || null)) {
                return data;
            }

            // EspoCRM derives name from these attributes, including during conversion.
            // Store the full user-entered name verbatim; do not guess surname order.
            return Object.assign(data, {
                salutationName: null,
                firstName: null,
                middleName: null,
                lastName: value
            });
        },

        validateFullNameLength: function () {
            // Existing split names can exceed one column's length. Only the full
            // name written into lastName must fit that column; never truncate it.
            const value = this.model.get('lastName') || '';
            const limit = this.model.getFieldParam('lastName', 'maxLength') || 100;

            if (Array.from(value).length <= limit) {
                return false;
            }

            this.showValidationMessage(
                this.translate('maxLength', 'fieldValidationExplanations'),
                '[data-name="' + this.name + '"]'
            );

            return true;
        }
    });
});
