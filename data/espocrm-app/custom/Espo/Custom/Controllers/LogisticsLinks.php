<?php
namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;

/** Financial relations must use guarded record saves, including bulk operations. */
trait LogisticsLinks
{
    private function guardLogisticsLink(Request $request): void
    {
        if (in_array($request->getRouteParam('link'),
            ['parcels','shipment','order','orderItems','account','cParcels','cShipments','cOrders'],true)) {
            throw new BadRequest('Hãy sửa trường liên kết trên bản ghi hoặc dùng Thêm kiện hàng; phiếu đã chốt phải hủy/hoàn trước.');
        }
    }
    public function postActionCreateLink(Request $request): bool
    { $this->guardLogisticsLink($request); return parent::postActionCreateLink($request); }
    public function deleteActionRemoveLink(Request $request): bool
    { $this->guardLogisticsLink($request); return parent::deleteActionRemoveLink($request); }
}
