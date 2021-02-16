<?php


namespace Happy\Clock\Models;

use Illuminate\Database\Eloquent\Model;

class PayClockUserErrorLog extends Model
{
    protected $table = 'pay_clock_user_error_log';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = ['clock_day_id', 'clock_day', 'red_money', 'order_no', 'uid', 'error_msg',
        'status', 'pay_result', 'create_time', 'update_time'
    ];

    const STATE_NOT_REPAIRED = 1;
    const STATE_REPAIRED = 2;

    public static $stateMapping = [
        self::STATE_NOT_REPAIRED => '未修复',
        self::STATE_REPAIRED => '已修复'
    ];
}
