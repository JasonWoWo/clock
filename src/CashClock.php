<?php


namespace Happy\Clock;

use Happy\Clock\Service\ClockService;

/**
 * 打卡支付核心
 * Class CashClock
 * @package Happy\Raffle
 */
class CashClock
{
    protected $clockService = null;

    public function __construct(ClockService $service)
    {
        $this->clockService = $service;
    }


    /**
     * STEP-ONE 2-1-0 参与打卡支付 原趣抽奖[api/ClockTen/userClockPay]
     * @param $uid
     * @param array $extra 必填参数信息：mobile , nickname , openId
     * @return array
     */
    public function generateClockOrder($uid, $extra = [])
    {
        return $this->clockService->generateOrder($uid, $extra);
    }


    /**
     * STEP-ONE 2-1-1 打卡支付结果 原趣抽奖[api/ClockTen/clockPayNotify]
     * @param $orderNo
     * @param array $extra 必须参数： amount(回调金额,单位：分), buyerId(支付用户openid或source_uid),
     *                     notify_time(回调通知时间), content(回调内容数据块), nickname(昵称), mobile(手机号)
     * @return array
     */
    public function notifyClockOrder($orderNo, $extra = [])
    {
        return $this->clockService->notifyPayInfo($orderNo, $extra);
    }

    // api/ClockTen/userRed 用户领取红包

    /**
     * STEP-ONE 6 用户在早上8点05分后，开始拆红包
     * @param $uid
     * @param \Closure $handler
     * @return array
     */
    public function userOpenRed($uid, $handler)
    {
        return $this->clockService->userDrawRed($uid, $handler);
    }
}
