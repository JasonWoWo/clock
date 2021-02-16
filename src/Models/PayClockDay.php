<?php


namespace Happy\Clock\Models;

use Illuminate\Database\Eloquent\Model;

class PayClockDay extends Model
{
    const CLOCK_STATE_INIT = 0; // 未参与
    const CLOCK_STATE_OUT = 1; // 已参与未打卡
    const CLOCK_STATE_IN = 2; // 已参与已打卡

    const RED_RELEASE_ON = 2; // 已领奖
    const RED_RELEASE_OFF = 1; // 未领奖

    protected $table = "pay_clock_day";

    public $timestamps = false;

    protected $primaryKey = 'id';

    protected $fillable = ['uid', 'nickname', 'mobile', 'source_uid', 'red_status',
        'clock_day', 'order_no', 'order_money', 'clock_money', 'clock_status', 'clock_time',
        'pay_result', 'red_time', 'create_time', 'update_time'
    ];

    public function getStatusText($payClockDay = [])
    {
        $statusText = "";
        if (date('Y-m-d H:i:s') > date('Y-m-d 08:00:00')) {  // 8点之后
            if ($payClockDay['clock_day'] == strtotime(date('Y-m-d 00:00:00'))) {   // 今天的打卡都是待打卡
                $statusText = "待打卡";
            } else {  // 如果是以前的
                $statusText = $payClockDay['clock_status'] == self::CLOCK_STATE_IN ? ($payClockDay['red_status'] == self::RED_RELEASE_ON ? "已领奖" : "未领奖") : "挑战失败";
            }
        } else {  // 8点之前
            if ($payClockDay['clock_day'] == strtotime(date('Y-m-d 00:00:00'))) {    // 今天的都是待打卡
                $statusText = "待打卡";
            } elseif ($payClockDay['clock_day'] == strtotime(date("Y-m-d 00:00:00", strtotime("-1 day")))) {  // 昨天的记录根据状态判断
                if ($payClockDay['clock_status'] == self::CLOCK_STATE_OUT && $payClockDay['clock_money'] == 0) {
                    $statusText = "待打卡";
                } elseif ($payClockDay['clock_status'] == self::CLOCK_STATE_IN && $payClockDay['red_status'] == self::RED_RELEASE_OFF) {
                    $statusText = "待开奖";
                }
            } else {  // 昨天之前的记录
                $statusText = $payClockDay['clock_status'] == self::CLOCK_STATE_IN ? ($payClockDay['red_status'] == self::CLOCK_STATE_IN ? "已领奖" : "未领奖") : "挑战失败";
            }
        }
        return $statusText;
    }
}
