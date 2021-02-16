<?php


namespace Happy\Clock\Models;

use Illuminate\Database\Eloquent\Model;

class PayClockOrder extends Model
{
    protected $table = 'pay_clock_order';

    public $timestamps = false;

    protected $primaryKey = 'id';

    const PAY_WAIT = 1;
    const PAY_ON = 2;

    public static $orderStatus = [
        self::PAY_WAIT => '待支付',
        self::PAY_ON => '已支付',
    ];

    const REFUND_WAIT = 1;
    const REFUND_SUCCESS = 2;

    public static $refundStatus = [
        self::REFUND_WAIT => '未退款',
        self::REFUND_SUCCESS => '已退款',
    ];

    protected $fillable = ['order_no', 'clock_day', 'user_id', 'buyer_id', 'order_status',
        'pay_amount', 'pay_result', 'pay_time', 'create_time', 'update_time'
    ];

    public function getOrderStatus($status)
    {
        return self::$orderStatus[$status];
    }

    public function getRefundStatus($status)
    {
        return self::$refundStatus[$status];
    }
}
