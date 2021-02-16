<?php


namespace Happy\Clock\Models;

use Illuminate\Database\Eloquent\Model;

class PayClockUser extends Model
{
    protected $table = 'pay_clock_user';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = ['uid', 'nickname', 'mobile', 'source_uid', 'is_remind', 'spend_money',
        'income_money', 'clock_max_money', 'clock_num', 'clock_day_num', 'long_day_num', 'yesterday_time',
        'create_time', 'update_time'];
}
