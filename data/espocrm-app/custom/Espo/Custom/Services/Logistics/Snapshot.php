<?php
namespace Espo\Custom\Services\Logistics;

final class Snapshot
{
    public static function html(array $s): string
    {
        $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = static fn($v) => number_format((float) $v, 2, ',', '.') . ' VND';
        $html = '<h1>PHIẾU XUẤT KHO</h1><p>Số phiếu: ' . $e($s['number']) .
            '<br>Khách hàng: ' . $e($s['accountCode'] . ' — ' . $s['accountName']) .
            '<br>Ngày lập: ' . $e($s['date']) . '</p>';
        $html .= '<table border="1" cellpadding="5"><tr><th>Đơn hàng</th><th>Giá trị thực tế</th>' .
            '<th>Giá trị khai báo</th><th>Phí dịch vụ</th><th>Phí đầu mục</th><th>Ủy thác</th>' .
            '<th>Thuế NK</th><th>VAT</th><th>Đã đặt cọc</th><th>Còn lại</th></tr>';
        foreach ($s['orders'] as $o) {
            $html .= '<tr><td>' . $e($o['name']) . '</td>';
            foreach (['totalOrderValueVnd','declaredValueVnd','serviceFeeVnd','documentFeeVnd',
                'entrustmentVnd','importTaxVnd','vatVnd','depositAmountVnd','remainingAmountVnd'] as $field) {
                $html .= '<td>' . $money($o[$field] ?? 0) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table><br><table border="1" cellpadding="5"><tr><th>Tracking</th><th>Số kiện</th><th>kg</th><th>m³</th></tr>';
        foreach ($s['parcels'] as $p) {
            $html .= '<tr><td>' . $e($p['name']) . '</td><td>' . $e($p['packageCount']) .
                '</td><td>' . $e($p['weight']) . '</td><td>' . $e($p['volume']) . '</td></tr>';
        }
        $html .= '</table><p>Cước vận chuyển: ' . $money($s['freight']) .
            '<br>Phụ thu: ' . $money($s['additional']) . '<br>Nội dung phụ thu: ' . $e($s['surcharge']) .
            '<br>Giá trị riêng phiếu: ' . $money($s['amount']) .
            '<br>Công nợ cũ tại lúc lập (âm = tiền dư): ' . $money($s['oldBalance']) .
            '<br><strong>Tổng đề nghị thanh toán: ' . $money($s['requested']) . '</strong></p>';
        return $html;
    }
}
