<?php
namespace Espo\Custom\Services\Logistics;

use Espo\Core\Exceptions\BadRequest;

final class Money
{
    public static function minor(mixed $value): int
    {
        if (!is_numeric($value) || !is_finite((float) $value) || abs((float) $value) > 1000000000000) {
            throw new BadRequest('Số tiền không hợp lệ.');
        }
        return (int) round((float) $value * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function major(int $value): float { return $value / 100; }

    public static function positive(mixed $value): int
    {
        $amount = self::minor($value);
        if ($amount <= 0) { throw new BadRequest('Số tiền phải lớn hơn 0.'); }
        return $amount;
    }

    public static function percent(mixed $base, mixed $rate): float
    {
        if (!is_numeric($rate) || !is_finite((float) $rate) || $rate < 0 || $rate > 100) {
            throw new BadRequest('Tỷ lệ phải nằm trong khoảng 0–100%.');
        }
        $minor = self::minor($base);
        if ($minor < 0) { throw new BadRequest('Giá trị tính phí không được âm.'); }
        return self::major((int) round($minor * (float) $rate / 100, 0, PHP_ROUND_HALF_UP));
    }
}
