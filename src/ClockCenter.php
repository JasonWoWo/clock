<?php


namespace Happy\Clock;

use Happy\Clock\Service\ClockService;

/**
 * 打卡展示入口页面
 * Class ClockCenter
 * @package Happy\Raffle
 */
class ClockCenter
{
    protected $clockService = null;

    public function __construct(ClockService $service)
    {
        $this->clockService = $service;
    }

    /**
     * STEP-ONE 4-1-0 当用户未参与打卡，将在早上5点到8点参与打卡 原趣抽奖[api/ClockTen/userClock]
     * @param $uid
     * @return array
     */
    public function clock($uid)
    {
        return $this->clockService->userClock($uid);
    }

    /**
     * STEP—TWO 1.设置打卡提醒 原趣抽奖[api/ClockTen/userRemind]
     * @param $uid
     * @return array
     */
    public function userRemind($uid)
    {
        return $this->clockService->userRemind($uid);
    }

    //

    /**
     * STEP-ONE 3 参与报名后判断是否参与打卡活动 原趣抽奖[api/ClockTen/checkUserClock]
     * @param $uid
     * @return array
     */
    public function checkUserClock($uid)
    {
        return $this->clockService->checkUserClockStatus($uid);
    }

    /**
     * STEP-ONE 0 未授权下，打卡界面用户数据概览
     * @return array
     */
    public function init()
    {
        return $this->clockService->initSummary();
    }


    /**
     * STEP-ONE 1 打卡界面用户数据集合 原趣抽奖[api/ClockTen/clockUserDetail]
     * @param int $uid
     * @param array $extra 必传参数信息：nickname, mobile, openId
     * @return array
     */
    public function summary($uid, $extra = [])
    {
        return $this->clockService->summaryClock($uid, $extra);
    }

    /**
     * STEP-THREE 1 获取达人列表
     * @return array
     */
    public function topClock()
    {
        return $this->clockService->getTopUser();
    }

    /**
     * STEP-THREE 2 获取打卡榜单
     * @param $page
     * @param $limit
     * @return array
     */
    public function recordClock($page = 0, $limit = 10)
    {
        return $this->clockService->getTopRecord($page, $limit);
    }
}
