<?php
namespace Espo\Custom\Services\Logistics;

use Espo\ORM\Entity;
use Espo\Core\Exceptions\BadRequest;

final class OrderFees
{
    public static function apply(Entity $order): float
    {
        if (!$order->get('formalImport')) { return 0.0; }
        $declared = $order->get('declaredValueVnd');
        if ($declared === null) { throw new BadRequest('Vui lòng nhập giá trị khai báo (VND).'); }
        $document = $order->get('documentFeeVnd') ?? 0;
        if (Money::minor($document) < 0) { throw new BadRequest('Phí đầu mục không được âm.'); }
        $total = Money::minor($document);
        foreach (['entrustment', 'importTax', 'vat'] as $name) {
            $amount = Money::percent($declared, $order->get($name . 'Percent') ?? 0);
            $order->set($name . 'Vnd', $amount);
            $total += Money::minor($amount);
        }
        $order->set('declaredFeesVnd', Money::major($total));
        return Money::major($total);
    }
}
