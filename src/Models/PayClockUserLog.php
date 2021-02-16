<?php


namespace Happy\Clock\Models;

use Illuminate\Database\Eloquent\Model;

class PayClockUserLog extends Model
{
    protected $table = 'pay_clock_user_log';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = ['uid', 'clock_day', 'money_type', 'change_money', 'log_content',
        'create_time', 'update_time'];

    const CLOCK_RED = 2;
    const CLOCK_CHALLENGE = 1;

    public static $type = [
        self::CLOCK_CHALLENGE => '参与挑战',
        self::CLOCK_RED => '早起打卡红包',
    ];
}
