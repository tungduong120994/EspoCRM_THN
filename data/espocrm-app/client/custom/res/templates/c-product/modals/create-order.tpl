<div class="record-container">
    <div class="record">
        <div class="panel panel-default">
            <div class="panel-body">
                <div class="edit-container">
                    <div class="row">
                        <div class="cell form-group col-md-6" data-name="orderDate">
                            <label class="control-label">
                                <span class="label-text">{{translate 'Order Date' category='fields' scope='COrder'}}</span>
                                <span class="required-sign">*</span>
                            </label>
                            <div class="field" data-name="orderDate">{{{orderDate}}}</div>
                        </div>
                        <div class="cell form-group col-md-6" data-name="exchangeRate">
                            <label class="control-label">
                                <span class="label-text">{{translate 'Exchange Rate' category='fields' scope='COrder'}}</span>
                                <span class="required-sign">*</span>
                            </label>
                            <div class="field" data-name="exchangeRate">{{{exchangeRate}}}</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="cell form-group col-md-6" data-name="quantity">
                            <label class="control-label">
                                <span class="label-text">{{translate 'Quantity' category='fields' scope='COrderItem'}}</span>
                            </label>
                            <div class="field" data-name="quantity">{{{quantity}}}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
