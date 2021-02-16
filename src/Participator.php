<?php


namespace Happy\Clock;

use Happy\Clock\Service\ClockService;

/**
 * 参与者数据中心&个人设置
 * Class Participator
 * @package Happy\Raffle
 */
class Participator
{
    protected $clockService = null;

    public function __construct(ClockService $service)
    {
        $this->clockService = $service;
    }

    /**
     * STEP-TWO 4.用户打卡数据概览 原趣抽奖[api/ClockTen/userInfo]
     * @param $uid
     * @param array $extra 必传参数信息：nickname, mobile, openId
     * @return array
     */
    public function information($uid, $extra = [])
    {
        return $this->clockService->clockUserInfo($uid, $extra);
    }

    /**
     * STEP-TWO 3.我的战绩(用户打卡金额变动列表) 原趣抽奖[api/ClockTen/userMoneyInfo]
     * @param $uid
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function moneyFloatItems($uid, $page = 0, $limit = 10)
    {
        return $this->clockService->userClockItem($uid, $page, $limit);
    }

    /**
     * STEP-ONE 7.标记用户当日以弹窗 原趣抽奖[api/ClockTen/userAlert]
     * @param $uid
     * @return array
     */
    public function showToast($uid)
    {
        return $this->clockService->setClockUserAlert($uid);
    }


    /**
     * STEP-TWO 2.用户昨日参与信息 原趣抽奖[api/ClockTen/yesterdayClock]
     * @param $uid
     * @return array
     */
    public function yesterdayClock($uid)
    {
        return $this->clockService->yesterdayClock($uid);
    }
}
