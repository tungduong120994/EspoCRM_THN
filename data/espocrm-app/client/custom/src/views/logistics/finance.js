define('custom:views/logistics/finance', ['view', 'custom:helpers/logistics'], function (View, Helper) {
    return View.extend({
        templateContent: `
            <h3>Thu tiền và công nợ</h3>
            <div class="row"><div class="col-sm-4"><input class="form-control" data-input="q" placeholder="Mã / tên khách" value="{{q}}"></div>
            <div class="col-sm-2"><button class="btn btn-default" data-action="search">Tìm khách</button></div>
            <div class="col-sm-6"><select class="form-control" data-input="account"><option value="">Chọn khách hàng</option>{{#each accounts}}<option value="{{id}}">{{code}} — {{name}}</option>{{/each}}</select></div></div>
            <p>Hiển thị tối đa 50 khách phù hợp. Nhập mã hoặc tên để thu hẹp danh sách.</p>
            {{#if balance}}<h4>{{balance.accountCode}} — {{balance.accountName}}</h4>
            <div class="row"><div class="col-sm-4">Tổng còn nợ: <strong>{{balance.dueVnd}} VND</strong></div>
            <div class="col-sm-4">Tiền dư chưa phân bổ: <strong>{{balance.creditVnd}} VND</strong></div>
            <div class="col-sm-4">Công nợ ròng: <strong>{{balance.balanceVnd}} VND</strong></div></div><br>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>PXK</th><th>Trạng thái</th><th>Giá trị sau hoàn</th><th>Đã phân bổ</th><th>Còn nợ</th><th>Phân bổ lần này (VND)</th></tr></thead><tbody>
            {{#if balance.openingDueVnd}}<tr><td>Nợ đầu kỳ</td><td></td><td></td><td></td><td>{{balance.openingDueVnd}}</td><td><input type="number" min="0" step="0.01" class="form-control" data-allocation=""></td></tr>{{/if}}
            {{#each balance.shipments}}<tr><td>{{name}}</td><td>{{statusLabel}}</td><td>{{amountVnd}}</td><td>{{paidVnd}}</td><td>{{dueVnd}}</td><td>{{#if dueVnd}}<input type="number" min="0" max="{{dueVnd}}" step="0.01" class="form-control" data-allocation="{{id}}">{{/if}}</td></tr>{{/each}}
            </tbody></table></div>
            {{#if balance.canEdit}}<div class="row"><div class="col-sm-4"><label>Số tiền mới nhận (VND)</label><input type="number" min="0" step="0.01" class="form-control" data-input="amount"></div>
            <div class="col-sm-8"><label>Nội dung thu / tham chiếu chuyển khoản</label><input class="form-control" data-input="reason"></div></div><br>
            <button class="btn btn-primary" data-action="receipt">Ghi nhận thu tiền và phân bổ</button>
            <button class="btn btn-default" data-action="allocate">Phân bổ từ tiền dư đang có</button>
            <p>Nhập số phân bổ trên từng phiếu; phần tiền nhận còn lại tự giữ ở khách hàng. Không cần phân bổ hết ngay.</p>
            <details><summary>Số dư đầu kỳ / tiền chuyển từ hệ thống cũ</summary><br>
            <select class="form-control" data-input="openingKind"><option value="openingCredit">Khách còn tiền dư</option><option value="openingDebit">Khách còn nợ</option></select>
            <label>Số tiền (VND)</label><input type="number" min="0" step="0.01" class="form-control" data-input="openingAmount">
            <label>Lý do / tham chiếu đối soát</label><input class="form-control" data-input="openingReason"><br>
            <button class="btn btn-default" data-action="opening">Ghi số dư đầu kỳ</button></details>{{/if}}
            <h4>Lịch sử gần nhất</h4><table class="table"><thead><tr><th>Ngày (UTC)</th><th>Nghiệp vụ</th><th>Số tiền VND</th><th>Ghi chú</th><th>Tracking hoàn</th></tr></thead><tbody>
            {{#each balance.history}}<tr><td>{{createdAt}}</td><td>{{kindLabel}}</td><td>{{amountVnd}}</td><td>{{reason}}</td><td>{{#each tracking}}{{name}}<br>{{/each}}</td></tr>{{/each}}
            </tbody></table>{{else}}<p>Chọn khách hàng để xem công nợ và các phiếu cần thanh toán.</p>{{/if}}`,
        events: {'change [data-input="account"]': function () { this.selectAccount(); }},
        setup: function () {
            Helper.bind(this, ['search','receipt','allocate','opening']);
            this.events['change [data-input="account"]'] = () => this.selectAccount();
            this.accounts = []; this.q = ''; this.wait(this.searchAccounts());
        },
        data: function () { return {accounts: this.accounts, q: this.q, balance: this.balance}; },
        afterRender: function () {
            if (this.accountId) { this.$el.find('[data-input="account"]').val(this.accountId); }
            if (!this.balance || !this.balance.canEdit) { this.$el.find('[data-allocation]').prop('disabled', true); }
        },
        searchAccounts: async function () {
            try { this.accounts = (await Espo.Ajax.getRequest('CLogistics/accounts', {q: this.q})).list; }
            catch (e) { Helper.error(e); }
        },
        actionSearch: async function () { this.q = this.input('q'); await this.searchAccounts(); this.reRender(); },
        input: function (name) { return this.$el.find('[data-input="' + name + '"]').val(); },
        selectAccount: async function () {
            this.accountId = this.input('account'); this.balance = null;
            if (!this.accountId) { this.reRender(); return; }
            try { this.setBalance(await Espo.Ajax.getRequest('CLogistics/balance', {accountId: this.accountId})); }
            catch (e) { Helper.error(e); } this.reRender();
        },
        setBalance: function (b) {
            const kinds = {receipt:'Thu tiền',openingCredit:'Tiền dư đầu kỳ',openingDebit:'Nợ đầu kỳ',allocation:'Phân bổ',release:'Hoàn phân bổ về tiền dư',returnCredit:'Phiếu hoàn hàng'};
            b.history = b.history.map(x => ({...x, kindLabel: kinds[x.kind] || x.kind}));
            b.shipments = b.shipments.map(x => ({...x, statusLabel: {confirmed:'Đã xác nhận',cancelled:'Đã hủy'}[x.status] || x.status}));
            this.balance = b;
        },
        allocations: function () {
            return Array.from(this.element.querySelectorAll('[data-allocation]')).filter(e => Number(e.value) > 0)
                .map(e => ({shipmentId: e.dataset.allocation, amountVnd: e.value}));
        },
        perform: async function (operation, data) {
            if (this.busy || !this.accountId) { return; } this.busy = true;
            this.$el.find('button').prop('disabled', true);
            try { this.setBalance(await Helper.post(this, operation, {...data, accountId: this.accountId})); Espo.Ui.success('Đã ghi nhận'); this.reRender(); }
            catch (e) { Helper.error(e); } finally { this.busy = false; this.$el.find('button').prop('disabled', false); }
        },
        actionReceipt: function () { this.perform('receipt', {amountVnd: this.input('amount'), reason: this.input('reason'), allocations: this.allocations()}); },
        actionAllocate: function () { this.perform('allocate', {allocations: this.allocations()}); },
        actionOpening: function () { this.perform('opening', {kind: this.input('openingKind'), amountVnd: this.input('openingAmount'), reason: this.input('openingReason')}); }
    });
});
