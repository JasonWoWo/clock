<?php


namespace Happy\Clock\Models;

use Illuminate\Database\Eloquent\Model;

class PayClockSetting extends Model
{
    protected $table = 'pay_clock_setting';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = ['start_time', 'end_time', 'money_proportion', 'red_num', 'create_time', 'update_time'];
}
