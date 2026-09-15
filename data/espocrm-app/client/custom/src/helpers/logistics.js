define('custom:helpers/logistics', [], function () {
    return {
        bind: function (view, actions) {
            for (const action of actions) {
                view.addActionHandler(action, (event, target) => {
                    event.preventDefault();
                    return view['action' + action[0].toUpperCase() + action.slice(1)](target.dataset);
                });
            }
        },
        error: function (error) {
            const message = error && error.responseJSON && error.responseJSON.message;
            Espo.Ui.error(message || 'Không thể hoàn tất thao tác. Vui lòng kiểm tra dữ liệu hoặc quyền truy cập.');
        },
        post: async function (view, operation, data) {
            const key = 'logistics-request:' + view.getUser().id + ':' + operation + ':' + data.accountId;
            const fingerprint = JSON.stringify(data);
            let pending;
            try { pending = JSON.parse(sessionStorage.getItem(key)); } catch (e) { pending = null; }
            if (!pending || pending.fingerprint !== fingerprint) {
                pending = {fingerprint, requestKey: crypto.randomUUID()};
                sessionStorage.setItem(key, JSON.stringify(pending));
            }
            const result = await Espo.Ajax.postRequest('CLogistics/' + operation, {...data, requestKey: pending.requestKey});
            sessionStorage.removeItem(key);
            return result;
        },
        download: function (result) {
            const bytes = Uint8Array.from(atob(result.content), c => c.charCodeAt(0));
            const url = URL.createObjectURL(new Blob([bytes], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}));
            const a = document.createElement('a'); a.href = url; a.download = result.filename;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }
    };
});
