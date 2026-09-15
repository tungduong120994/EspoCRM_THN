define('custom:views/logistics/inventory', ['view', 'custom:helpers/logistics'], function (View, Helper) {
    return View.extend({
        templateContent: `
            <h3>Tồn kho — Kho Việt Nam</h3>
            <div class="row">
                <div class="col-sm-4"><label>Tìm mã / tên khách</label><input class="form-control" data-filter="q" value="{{q}}"></div>
                <div class="col-sm-3"><label>Ngày nhập từ</label><input type="date" class="form-control" data-filter="from" value="{{from}}"></div>
                <div class="col-sm-3"><label>Đến ngày</label><input type="date" class="form-control" data-filter="to" value="{{to}}"></div>
                <div class="col-sm-2"><br><button class="btn btn-primary" data-action="filter">Lọc</button></div>
            </div><br>
            <p>Số nhập theo khoảng ngày chọn. Tồn kho là số tracking hiện tại, bao gồm hàng hoàn; một tracking có thể có nhiều kiện.</p>
            <button class="btn btn-default" data-action="export">Xuất Excel .xlsx</button>
            {{#if accountId}}<button class="btn btn-default" data-action="back">Quay lại khách hàng</button>
            <h4>{{accountLabel}}</h4>
            <label><input type="checkbox" data-filter="receivedOnly" {{#if receivedOnly}}checked{{/if}}> Chỉ tracking nhập trong khoảng ngày chọn</label>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Tracking</th><th>Số kiện</th><th>kg</th><th>m³</th><th>Trạng thái</th><th>Ngày nhập (VN)</th><th>Ngày giao (VN)</th><th>Ngày hoàn (VN)</th></tr></thead><tbody>
            {{#each rows}}<tr><td>{{name}}</td><td>{{packageCount}}</td><td>{{weight}}</td><td>{{volume}}</td><td>{{statusLabel}}</td><td>{{receivedAt}}</td><td>{{deliveredAt}}</td><td>{{returnedAt}}</td></tr>{{/each}}
            </tbody></table></div>
            {{else}}
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Mã khách</th><th>Tên khách</th><th>Tracking nhập</th><th>kg nhập</th><th>m³ nhập</th><th>Tracking tồn</th><th>kg tồn</th><th>m³ tồn</th></tr></thead><tbody>
            {{#each rows}}<tr><td><button class="btn btn-link" data-action="tracking" data-id="{{id}}">{{code}}</button></td><td>{{name}}</td><td>{{receivedTracking}}</td><td>{{receivedWeight}}</td><td>{{receivedVolume}}</td><td>{{stockTracking}}</td><td>{{stockWeight}}</td><td>{{stockVolume}}</td></tr>{{/each}}
            </tbody></table></div>{{/if}}
            {{#unless rows.length}}<p>Không có dữ liệu phù hợp.</p>{{/unless}}
            <button class="btn btn-default" data-action="previous" {{#unless hasPrevious}}disabled{{/unless}}>Trước</button>
            <span>{{start}}–{{end}} / {{total}}</span>
            <button class="btn btn-default" data-action="next" {{#unless hasNext}}disabled{{/unless}}>Sau</button>`,
        setup: function () {
            Helper.bind(this, ['filter','tracking','back','previous','next','export']);
            this.filters = {q: '', from: '', to: ''}; this.rows = []; this.offset = 0; this.total = 0; this.limit = 50;
            this.wait(this.load());
        },
        data: function () {
            return {...this.filters, accountId: this.accountId, accountLabel: this.accountLabel,
                receivedOnly: this.receivedOnly, rows: this.rows, total: this.total,
                start: this.total ? this.offset + 1 : 0, end: Math.min(this.total, this.offset + this.limit),
                hasPrevious: this.offset > 0, hasNext: this.offset + this.limit < this.total};
        },
        params: function () {
            return {...this.filters, offset: this.offset, accountId: this.accountId || '', receivedOnly: this.receivedOnly ? 'true' : 'false'};
        },
        load: async function () {
            try {
                const r = await Espo.Ajax.getRequest('CLogistics/' + (this.accountId ? 'tracking' : 'inventory'), this.params());
                this.rows = r.list.map(row => ({...row, statusLabel: {undelivered:'Chưa giao',delivered:'Đã giao',returned:'Đã trả lại'}[row.status]}));
                this.total = r.total; this.limit = r.limit;
            } catch (e) { this.rows = []; this.total = 0; Helper.error(e); }
        },
        refresh: async function () { await this.load(); this.reRender(); },
        actionFilter: function () {
            for (const key of ['q','from','to']) { this.filters[key] = this.$el.find('[data-filter="' + key + '"]').val(); }
            this.receivedOnly = this.$el.find('[data-filter="receivedOnly"]').is(':checked'); this.offset = 0; this.refresh();
        },
        actionTracking: function (data) {
            const row = this.rows.find(r => r.id === data.id); if (!row) { return; }
            this.accountId = data.id; this.accountLabel = row.code + ' — ' + row.name; this.offset = 0; this.receivedOnly = false; this.refresh();
        },
        actionBack: function () { this.accountId = null; this.offset = 0; this.refresh(); },
        actionPrevious: function () { this.offset = Math.max(0, this.offset - this.limit); this.refresh(); },
        actionNext: function () { this.offset += this.limit; this.refresh(); },
        actionExport: async function () {
            if (this.exporting) { return; } this.exporting = true;
            try { Helper.download(await Espo.Ajax.getRequest('CLogistics/xlsx', this.params())); }
            catch (e) { Helper.error(e); } finally { this.exporting = false; }
        }
    });
});
