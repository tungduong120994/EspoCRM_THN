define('custom:handlers/select-related/product-by-order-account', ['handlers/select-related', 'model'], function (Dep, Model) {

    return class extends Dep {

        getFilters(model) {
            let filters = {};
            
            // Get the order's account ID
            const orderId = model.get('orderId');

            if (orderId) {
                // Fetch the order to get its accountId
                return new Promise((resolve) => {
                    Espo.Ajax.getRequest(`COrder/${orderId}`, {
                        select: 'accountId,accountName'
                    }).then((orderData) => {
                        const accountId = orderData.accountId;
                        const accountName = orderData.accountName;
                        
                        if (accountId) {
                            filters.account = {
                                attribute: 'accountId',
                                type: 'equals',
                                value: accountId,
                                data: {
                                    type: 'is',
                                    nameValue: accountName
                                }
                            };
                        }
                        
                        resolve({
                            advanced: filters
                        });
                    }).catch(() => {
                        // If fetch fails, return empty filters
                        resolve({
                            advanced: filters
                        });
                    });
                });
            }

            // No order selected, return no filters (show all products)
            return Promise.resolve({
                advanced: filters
            });
        }
    };
});
