"""Maintain the source metadata for the logistics extension. No DB connection."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RES = ROOT / 'data/espocrm-app/custom/Espo/Custom/Resources'

def read(path):
    return json.loads(path.read_text(encoding='utf-8')) if path.exists() else {}

def write(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')

def money(readonly=False):
    return dict(type='currency', onlyDefaultCurrency=True, isCustom=True, readOnly=readonly)

def add_fields(entity, fields):
    p = RES / f'metadata/entityDefs/{entity}.json'
    d = read(p)
    d.setdefault('fields', {}).update(fields)
    for name, field in fields.items():
        if field['type'] == 'currency':
            d['fields'][name + 'Currency'] = {'default': 'VND'}
    write(p, d)

order_fields = {
    'formalImport': {'type': 'bool', 'default': False, 'isCustom': True},
    'serviceFeeRatePercent': {'type': 'float', 'min': 0, 'max': 100, 'isCustom': True, 'tooltip': True},
    'declaredValueVnd': {**money(), 'min': 0},
    'documentFeeVnd': {**money(), 'min': 0, 'default': 0},
    'declaredFeesVnd': money(True),
}
for name in ['entrustment', 'importTax', 'vat']:
    order_fields[name + 'Percent'] = {'type': 'float', 'min': 0, 'max': 100, 'default': 0, 'isCustom': True}
    order_fields[name + 'Vnd'] = money(True)
add_fields('COrder', order_fields)
add_fields('CShipment', {
    'workflowStatus': {'type': 'enum', 'options': ['', 'draft', 'confirmed', 'cancelled'], 'default': '',
                       'readOnly': True, 'isCustom': True, 'maxLength': 20},
    'confirmedAt': {'type': 'datetime', 'readOnly': True, 'isCustom': True},
    'cancelledAt': {'type': 'datetime', 'readOnly': True, 'isCustom': True},
    'postedAmountVnd': money(True), 'paidAmountVnd': money(True), 'outstandingAmountVnd': money(True),
    'oldBalanceSnapshotVnd': money(True), 'requestedTotalSnapshotVnd': money(True),
    'invoiceSnapshot': {'type': 'text', 'readOnly': True, 'isCustom': True},
    'invoiceSnapshotHtml': {'type': 'text', 'readOnly': True, 'isCustom': True},
    'additionalOrders': {'type': 'array', 'isCustom': True, 'view': 'custom:views/c-shipment/fields/additional-orders'},
})
p = RES / 'metadata/entityDefs/CShipment.json'
d = read(p)
d['links'].pop('additionalOrders', None)
write(p, d)
add_fields('CParcel', {
    'deliveredAt': {'type': 'datetime', 'readOnly': True, 'isCustom': True},
    'returnedAt': {'type': 'datetime', 'readOnly': True, 'isCustom': True},
})

# Immutable audit records are written only by the transactional action service.
definitions = {
    'CLogisticsEntry': {
        'kind': {'type': 'enum', 'options': ['receipt','openingCredit','openingDebit','allocation','release','returnCredit']},
        'amountVnd': money(True), 'shipment': {'type': 'link'}, 'receipt': {'type': 'link'},
        'reason': {'type': 'text'}, 'trackingSnapshot': {'type': 'text'},
    },
    'CLogisticsOperation': {
        'requestKey': {'type': 'varchar', 'maxLength': 80}, 'payloadHash': {'type': 'varchar', 'maxLength': 64},
        'result': {'type': 'text'},
    },
    'CShipmentCharge': {
        'shipment': {'type': 'link'}, 'order': {'type': 'link'}, 'amountVnd': money(True),
        'active': {'type': 'bool'},
    },
    'CStockEvent': {
        'parcel': {'type': 'link'}, 'shipment': {'type': 'link'},
        'kind': {'type': 'enum', 'options': ['delivered', 'cancelled', 'unlinked', 'returned']},
        'reason': {'type': 'text'},
    },
}
for entity, fields in definitions.items():
    fields = {'name': {'type': 'varchar', 'maxLength': 255}, 'account': {'type': 'link'},
              'createdAt': {'type': 'datetime'}, 'createdBy': {'type': 'link'}, **fields}
    for field in fields.values():
        field['readOnly'] = True
    for name, field in list(fields.items()):
        if field['type'] == 'currency': fields[name + 'Currency'] = {'type': 'enum', 'default': 'VND', 'options': ['VND'], 'readOnly': True}
    links = {'account': {'type': 'belongsTo', 'entity': 'Account'},
             'createdBy': {'type': 'belongsTo', 'entity': 'User'}}
    for field, target in [('shipment','CShipment'),('order','COrder'),('parcel','CParcel'),('receipt','CLogisticsEntry')]:
        if field in fields: links[field] = {'type': 'belongsTo', 'entity': target}
    data = {'fields': fields, 'links': links, 'collection': {'orderBy': 'createdAt', 'order': 'desc'},
            'indexes': {'accountCreated': {'columns': ['accountId','createdAt']}}}
    if entity == 'CLogisticsOperation':
        data['indexes']['requestKey'] = {'columns': ['requestKey'], 'unique': True}
    write(RES / f'metadata/entityDefs/{entity}.json', data)
    add_fields(entity, {})
    # No standard CRUD/list endpoints: account-filtered custom endpoints expose
    # only the appropriate inventory/finance projection.
    write(RES / f'metadata/scopes/{entity}.json', {'entity': True, 'tab': False, 'layouts': False,
          'acl': True, 'importable': False, 'customizable': False, 'module': 'Custom', 'type': 'Base'})

for entity in ['CShipment','CParcel','COrder','COrderItem']:
    p=RES/f'metadata/entityDefs/{entity}.json'; d=read(p)
    d['repositoryClassName']='Espo\\Custom\\Repositories\\TransactionalLogistics'
    write(p,d)

for scope, actions in [('CInventory', ['read','export']), ('CFinance', ['read','edit'])]:
    write(RES / f'metadata/scopes/{scope}.json', {
        'entity': False, 'tab': True, 'acl': True, 'aclFieldLevelDisabled': True,
        'aclActionList': actions, 'aclActionLevelListMap': {a: ['all','own','no'] for a in actions},
        'module': 'Custom', 'customizable': False,
    })
    write(RES / f'metadata/clientDefs/{scope}.json', {
        'controller': 'custom:controllers/' + ('inventory' if scope == 'CInventory' else 'finance'),
        'iconClass': 'fas fa-warehouse' if scope == 'CInventory' else 'fas fa-wallet',
    })

for layout in ['detail','detailSmall']:
    p = RES / f'layouts/COrder/{layout}.json'
    if p.exists():
        d = read(p)
        for panel in d:
            for row in panel.get('rows', []):
                for cell in row:
                    if isinstance(cell, dict) and cell.get('name') == 'serviceFeeVndPercent':
                        cell['name'] = 'serviceFeeRatePercent'
        if not any(x.get('label') == 'DeclaredFees' for x in d):
            d.insert(2, {'label': 'DeclaredFees', 'style': 'default', 'rows': [
                [{'name':'formalImport'}, {'name':'declaredValueVnd'}],
                [{'name':'documentFeeVnd'}, False],
                [{'name':'entrustmentPercent'}, {'name':'entrustmentVnd'}],
                [{'name':'importTaxPercent'}, {'name':'importTaxVnd'}],
                [{'name':'vatPercent'}, {'name':'vatVnd'}],
                [{'name':'declaredFeesVnd'}, False],
            ]})
        write(p, d)
    p = RES / f'layouts/CShipment/{layout}.json'
    d = read(p)
    if not any(x.get('label') == 'ShipmentWorkflow' for x in d):
        d.insert(1, {'label':'ShipmentWorkflow', 'style':'default', 'rows':[
            [{'name':'account'}, {'name':'workflowStatus'}],
            [{'name':'order'}, {'name':'additionalOrders'}],
            [{'name':'confirmedAt'}, {'name':'paidAmountVnd'}],
            [{'name':'oldBalanceSnapshotVnd'}, {'name':'outstandingAmountVnd'}],
            [{'name':'postedAmountVnd'}, {'name':'requestedTotalSnapshotVnd'}],
        ]})
    write(p, d)
    p = RES / f'layouts/CParcel/{layout}.json'; d = read(p)
    if not any(x.get('label') == 'WarehouseDates' for x in d):
        d.append({'label':'WarehouseDates','rows':[[{'name':'createdAt'},{'name':'deliveredAt'}],[{'name':'returnedAt'},False]]})
    write(p, d)
p = RES / 'layouts/CParcel/list.json'; d = read(p)
if not any(x.get('name') == 'deliveredAt' for x in d): d.append({'name':'deliveredAt'})
write(p,d)

vi = {
    'COrder': {'formalImport':'Đơn chính ngạch','declaredValueVnd':'Giá trị khai báo (VND)',
              'documentFeeVnd':'Phí đầu mục (VND)','serviceFeeRatePercent':'Phí dịch vụ (%)',
              'entrustmentPercent':'Ủy thác (%)','entrustmentVnd':'Phí ủy thác (VND)',
              'importTaxPercent':'Thuế NK (%)','importTaxVnd':'Thuế NK (VND)',
              'vatPercent':'VAT (%)','vatVnd':'VAT (VND)','declaredFeesVnd':'Tổng phí khai báo (VND)'},
    'CShipment': {'workflowStatus':'Trạng thái phiếu','confirmedAt':'Ngày xác nhận','cancelledAt':'Ngày hủy',
                 'additionalOrders':'Các đơn bổ sung','postedAmountVnd':'Giá trị phiếu đã chốt (VND)',
                 'paidAmountVnd':'Đã phân bổ thanh toán (VND)','outstandingAmountVnd':'Phiếu còn nợ (VND)',
                 'oldBalanceSnapshotVnd':'Công nợ cũ lúc lập phiếu (VND)',
                 'requestedTotalSnapshotVnd':'Tổng đề nghị thanh toán lúc lập phiếu (VND)'},
    'CParcel': {'createdAt':'Ngày nhập mã (kho VN)','deliveredAt':'Ngày giao khách gần nhất','returnedAt':'Ngày hoàn kho gần nhất'},
}
for lang in ['vi_VN','en_US','fr_FR']:
    # Vietnamese business labels are also supplied to the existing French-login
    # users; these are operational labels, not unlocalized internal field names.
    for entity, labels in vi.items():
        p = RES / f'i18n/{lang}/{entity}.json'; d=read(p); d.setdefault('fields',{}).update(labels)
        d.setdefault('labels',{}).update({'DeclaredFees':'Phí khai báo','ShipmentWorkflow':'Phiếu xuất kho và thanh toán','WarehouseDates':'Ngày nhập / giao / hoàn'})
        if entity == 'CShipment':
            d.setdefault('options',{})['workflowStatus']={'':'Phiếu cũ','draft':'Nháp','confirmed':'Đã xác nhận','cancelled':'Đã hủy'}
        if entity == 'COrder':
            d.setdefault('tooltips',{})['serviceFeeRatePercent']='Nhập 1,5 để tính 1,5%. Giá trị cũ được giữ nguyên khi chưa thay tỷ lệ.'
        write(p,d)
    p=RES/f'i18n/{lang}/Global.json'; d=read(p)
    d.setdefault('scopeNames',{}).update({'CInventory':'Tồn kho','CFinance':'Thu tiền và công nợ'})
    d.setdefault('scopeNamesPlural',{}).update({'CInventory':'Tồn kho','CFinance':'Thu tiền và công nợ'})
    write(p,d)

# Keep nullable stored fractions backwards compatible with a percent display view.
p=RES/'metadata/entityDefs/COrder.json'; d=read(p)
d['fields']['serviceFeeRatePercent']['view']='custom:views/c-order/fields/service-fee-percent'
write(p,d)

p=RES/'routes.json'; routes=read(p) or []
for method in ['get','post']:
    route={'route':'/CLogistics/:operation','method':method,'actionClassName':'Espo\\Custom\\Classes\\Api\\Logistics'}
    if route not in routes: routes.append(route)
write(p,routes)
# Form visibility mirrors server validation without changing old stored values.
p=RES/'metadata/clientDefs/COrder.json'; d=read(p)
fields=d.setdefault('dynamicLogic',{}).setdefault('fields',{})
formal={'conditionGroup':[{'type':'isTrue','attribute':'formalImport'}]}
for name in ['declaredValueVnd','documentFeeVnd','entrustmentPercent','entrustmentVnd',
             'importTaxPercent','importTaxVnd','vatPercent','vatVnd','declaredFeesVnd']:
    fields.setdefault(name,{})['visible']=formal
fields['declaredValueVnd']['required']=formal
write(p,d)
p=RES/'metadata/clientDefs/CShipment.json'; d=read(p)
fields=d.setdefault('dynamicLogic',{}).setdefault('fields',{})
fields.setdefault('paid',{})['readOnly']={'conditionGroup':[{'type':'isNotEmpty','attribute':'workflowStatus'}]}
posted={'conditionGroup':[{'type':'or','value':[
    {'type':'equals','attribute':'workflowStatus','value':'confirmed'},
    {'type':'equals','attribute':'workflowStatus','value':'cancelled'}]}]}
for name in ['account','order','additionalOrders','unitDeliveryPriceVnd','deliveryPricingMode',
             'unitDeliveryPricePerKgVnd','unitDeliveryPricePerM3Vnd','additionalAmountVnd','surcharge']:
    fields.setdefault(name,{})['readOnly']=posted
write(p,d)
print('Logistics metadata updated (source only).')
