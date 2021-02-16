<?php


namespace Happy\Clock\Models;

use Illuminate\Database\Eloquent\Model;

class PayClock extends Model
{
    protected $table = 'pay_clock';

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = ['clock_day', 'user_num', 'success_user_num', 'failure_user_num',
        'clock_money', 'create_time', 'update_time'];

    public function getSuccessProportionAttr($userNum, $successUserNum)
    {
        return $userNum ? (round($successUserNum/$userNum, 4) * 100) . '%' : '0%';
    }

    public function getStatusTextAttr($clockDay)
    {
        if (time() >= ($clockDay + 24*60*60)){
            $statusText = "已结束";
        } elseif (date("Y-m-d H:i:s") >= date("Y-m-d 08:00:00", $clockDay) && date("Y-m-d H:i:s") < date("Y-m-d 24:00:00", $clockDay)) {
            $statusText = "报名中";
        } elseif (date("Y-m-d H:i:s") >= date("Y-m-d 00:00:00", $clockDay) && date("Y-m-d H:i:s") < date("Y-m-d 05:00:00", $clockDay)) {
            $statusText = "待打卡";
        } elseif (date("Y-m-d H:i:s") >= date("Y-m-d 05:00:00", $clockDay) && date("Y-m-d H:i:s") < date("Y-m-d 08:00:00", $clockDay)) {
            $statusText = "可打卡";
        } else {
            $statusText = "未开始";
        }
        return $statusText;
    }

}
