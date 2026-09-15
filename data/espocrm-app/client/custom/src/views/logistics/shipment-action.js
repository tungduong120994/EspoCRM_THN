define('custom:views/logistics/shipment-action', ['views/modal','custom:helpers/logistics'], function (Modal, Helper) {
    return Modal.extend({
        templateContent: `
            <p>{{explanation}}</p>
            {{#if isReturn}}<label>Số tiền giảm công nợ (VND)</label><input type="number" min="0" step="0.01" class="form-control" data-input="amount" value="0">
            <h5>Chọn tracking hoàn về kho (hoàn toàn bộ số kiện của tracking)</h5>
            {{#each parcels}}<label style="display:block"><input type="checkbox" data-parcel="{{id}}"> {{name}}</label>{{/each}}
            {{#unless parcels.length}}<p>Không có tracking đã giao còn thuộc phiếu này.</p>{{/unless}}{{/if}}
            {{#unless isConfirm}}<label>Lý do</label><textarea class="form-control" data-input="reason"></textarea>{{/unless}}`,
        setup: function () {
            this.operation = this.options.operation; this.shipment = this.options.shipment;
            this.headerText = {confirm:'Xác nhận PXK',cancel:'Hủy PXK',return:'Lập phiếu hoàn hàng'}[this.operation];
            this.buttonList = [{name:'submit',label:this.headerText,style:'primary'},{name:'cancel',label:'Cancel'}];
            this.parcels = [];
            if (this.operation === 'return') { this.wait(this.loadParcels()); }
        },
        data: function () {
            return {isConfirm:this.operation==='confirm',isReturn:this.operation==='return',parcels:this.parcels,
                explanation:{confirm:'Chốt các đơn, cước và công nợ hiện tại lên phiếu. Kiện chỉ chuyển Đã giao khi thanh toán đủ.',
                    cancel:'Hủy giá trị phải thu của phiếu; tiền đã phân bổ trở lại số dư khách hàng. Tracking còn trên phiếu trở về Chưa giao.',
                    return:'Ghi tracking nhập lại kho và số tiền giảm công nợ. Tiền dư sau hoàn được giữ cho khách.'}[this.operation]};
        },
        loadParcels: async function () {
            try { this.parcels = (await Espo.Ajax.getRequest('CLogistics/return-options', {accountId:this.shipment.get('accountId'),shipmentId:this.shipment.id})).list; }
            catch(e) { Helper.error(e); }
        },
        actionSubmit: async function () {
            if (this.busy) { return; } this.busy=true; this.disableButton('submit');
            const data={accountId:this.shipment.get('accountId'),shipmentId:this.shipment.id};
            if (this.operation !== 'confirm') { data.reason=this.$el.find('[data-input="reason"]').val(); }
            if (this.operation === 'return') {
                data.amountVnd=this.$el.find('[data-input="amount"]').val();
                data.parcelIds=Array.from(this.element.querySelectorAll('[data-parcel]:checked')).map(e=>e.dataset.parcel);
            }
            try { await Helper.post(this,this.operation,data); this.trigger('done'); this.close(); Espo.Ui.success('Đã ghi nhận'); }
            catch(e) { Helper.error(e); } finally { this.busy=false; this.enableButton('submit'); }
        }
    });
});
