<?php


namespace Happy\Clock\Service;

use Carbon\Carbon;
use Happy\Clock\Models\PayClock;
use Happy\Clock\Models\PayClockDay;
use Happy\Clock\Models\PayClockOrder;
use Happy\Clock\Models\PayClockSetting;
use Happy\Clock\Models\PayClockUser;
use Happy\Clock\Models\PayClockUserErrorLog;
use Happy\Clock\Models\PayClockUserLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClockService extends BaseService
{
    // 一天的时间戳
    const DAY_TIMESTAMP = 86400;

    /**
     * 业务服务定义的系统时间
     * @var float|int|string
     */
    protected $systemCurrentTimestamp = 0;

    public function __construct()
    {
        $this->systemCurrentTimestamp = Carbon::now()->timestamp;
    }

    /**
     * 获取首页界面的数据情况
     * @param $uid
     * @param array $extra
     * @return array
     */
    public function summaryClock($uid, $extra = [])
    {
        // 用户八点五分以前，看到都是昨日数据
        $checkTime = strtotime(date('Y-m-d 08:05:00', $this->systemCurrentTimestamp));
        $yesterday = strtotime(date('Y-m-d', strtotime('-1 day', $this->systemCurrentTimestamp)));
        $yesterdayEntity = PayClockDay::query()->where(['uid' => $uid, 'clock_day' => $yesterday])->first();
        // 当前时间 < 当日早上08:05分，同时，昨天有打卡数据，展示昨天的数据
        if (time() < $checkTime && $yesterdayEntity) {
            $this->data['summary'] = $this->prefixSummaryClock($uid, $extra);
            return $this->pipeline();
        }
        $isAlert = $this->checkUserAlert($uid);
        $isClockTime = strtotime(date('Y-m-d 05:00:00', strtotime('+1 day', $this->systemCurrentTimestamp))) - $this->systemCurrentTimestamp;
        // 昨日活动总金额
        $yesterdayClockInfo = $this->yesterdayInfo();
        $summary = [
            'is_remind' => 0, // 打卡提醒 0关闭 1开启
            'clock_status' => PayClockDay::CLOCK_STATE_INIT, //  0未参与 1已参与 2已打卡
            'user_num' => 0, // 参与人数
            'clock_user_num' => 0, // 打卡成功人数
            'clock_money' => 0, // 奖池金额
            'clock_time' => $isClockTime,
            'red_time' => 0, // 开奖倒计时
            'zero_time' => strtotime(date('Y-m-d 23:59:59', $this->systemCurrentTimestamp)) - $this->systemCurrentTimestamp,//零点开关,
            'is_start' => 1,
            'is_alert' => $isAlert ? 1 : 0,
            'yest_money' => isset($yesterdayClockInfo['clock_money']) ? $yesterdayClockInfo['clock_money'] / 100 : 0,
        ];
        $userEntity = PayClockUser::query()->where('uid', $uid)->select(['id', 'is_remind'])->first();
        if (is_null($userEntity)) {
            $this->addUserClock($uid, $extra);
        }
        $day = strtotime(date('Y-m-d', time()));
        $clockDayEntity = PayClockDay::query()->where(['uid' => $uid, 'clock_day' => $day])->select(['id', 'clock_status'])
            ->first();
        if (!is_null($clockDayEntity)) {
            $clockDay = $clockDayEntity->toArray();
            $summary['clock_status'] = $clockDay['clock_status'];
        }
        $clockEntity = PayClock::query()->where('clock_day', $day)->select(['id', 'user_num', 'clock_money'])
            ->first();
        if (!is_null($clockEntity)) {
            $clock = $clockEntity->toArray();
            $summary['user_num'] = $clock['user_num'];
            $summary['clock_money'] = empty($clock['clock_money']) ? 0 : $clock['clock_money'] / 100;
        }
        $this->data['summary'] = $summary;
        return $this->pipeline();
    }

    /**
     * 获取昨日的参与金额信息
     * @return array|mixed
     */
    private function yesterdayInfo()
    {
        $day = strtotime(date('Y-m-d', strtotime('-1 day', $this->systemCurrentTimestamp)));
        $key = Config::get('clock.clock_yesterday_cash_key') . $day;
        $cache = Cache::get($key);
        if ($cache) {
            return json_decode($cache, true);
        }
        $clockEntity = PayClock::query()->where('clock_day', $day)->select(['id', 'clock_money'])->first();
        if (is_null($clockEntity)) {
            return [];
        }
        $clock = $clockEntity->toArray();
        Cache::add($key, json_encode($clock), 600);
        return $clock;
    }

    /**
     * 设置打卡提醒
     * @param $uid
     * @return array
     */
    public function setClockUserAlert($uid)
    {
        $key = $this->getClockUserAlertKey($uid);
        Cache::set($key, 1, 7 * 24 * 3600);
        $this->data['is_setting'] = 1;
        return $this->pipeline();
    }

    /**
     * 用户当日是否弹窗
     * @param $uid
     * @return bool
     */
    private function checkUserAlert($uid)
    {
        $key = $this->getClockUserAlertKey($uid);
        return Cache::get($key) ? true : false;
    }

    private function getClockUserAlertKey($uid)
    {
        $day = strtotime('Y-m-d', $this->systemCurrentTimestamp);
        return Config::get('clock.prefix_alert_key') . $uid . "_" . $day;
    }

    //八点零五分以前显示昨日打卡数据
    private function prefixSummaryClock($uid, $extra = [])
    {
        $start = strtotime('Y-m-d 05:00:00', $this->systemCurrentTimestamp);
        $end = strtotime('Y-m-d 08:00:00', $this->systemCurrentTimestamp);
        $isClockTime = $start - $this->systemCurrentTimestamp; // 是否能参与打卡倒计时，当isClockTime=0时候，不展示倒计时
        // 昨日参加过的今日8点5分后才能参加下一轮，昨日未参加的今日参加不受任何限制
        $yesterday = strtotime(date('Y-m-d', strtotime('-1 day', $this->systemCurrentTimestamp)));
        $yesterdayEntity = PayClockDay::query()->where(['uid' => $uid, 'clock_day' => $yesterday])->first();
        $redTime = $yesterdayEntity ? (strtotime(date('Y-m-d 08:05:00', $this->systemCurrentTimestamp)) - $this->systemCurrentTimestamp) : 0;
        if ($this->systemCurrentTimestamp >= $start && $this->systemCurrentTimestamp <= $end) {
            $isClockTime = 0;
        }
        $summary = [
            'is_remind' => 0, // 打卡提醒 0关闭 1开启
            'clock_status' => PayClockDay::CLOCK_STATE_INIT, //  0未参与 1已参与 2已打卡
            'user_num' => 0, // 参与人数
            'clock_user_num' => 0, // 打卡成功人数
            'clock_money' => 0, // 奖池金额
            'clock_time' => $isClockTime,
            'red_time' => $redTime > 0 ? $redTime : 0, // 开奖倒计时
            'zero_time' => 0,
            'is_start' => 0,
            'is_alert' => 0,
            'yest_money' => 0
        ];
        $userEntity = PayClockUser::query()->where('uid', $uid)->select(['id', 'is_remind'])->first();
        if (is_null($userEntity)) {
            $this->addUserClock($uid, $extra);
        }
        $day = strtotime(date('Y-m-d', strtotime('-1 day', $this->systemCurrentTimestamp)));
        $clockDayEntity = PayClockDay::query()->where(['uid' => $uid, 'clock_day' => $day])->select(['id', 'clock_status'])
            ->first();
        if (!is_null($clockDayEntity)) {
            $clockDay = $clockDayEntity->toArray();
            $summary['clock_status'] = $clockDay['clock_status'];
        }
        $clockEntity = PayClock::query()->where('clock_day', $day)->select(['id', 'user_num', 'clock_money'])
            ->first();
        if (!is_null($clockEntity)) {
            $clock = $clockEntity->toArray();
            $summary['user_num'] = empty($clock['user_num']) ? 0 : $clock['user_num'];
            $summary['clock_money'] = empty($clock['clock_money']) ? 0 : $clock['clock_money'];
            $summary['yest_money'] = $summary['clock_money'];
        }
        $userClockInCounts = PayClockDay::query()->where(['clock_day' => $day, 'clock_status' => PayClockDay::CLOCK_STATE_IN])->count();
        $summary['clock_user_num'] = $userClockInCounts;
        return $summary;
    }

    /**
     * 支付后判断用户是否参与打卡活动
     * @param $uid
     * @return array
     */
    public function checkUserClockStatus($uid)
    {
        $day = strtotime(date('Y-m-d', $this->systemCurrentTimestamp));
        $userPayClockEntity = PayClockDay::query()
            ->where(['uid' => $uid, 'clock_day' => $day, 'clock_status' => PayClockDay::CLOCK_STATE_IN])->first();
        $isCheckStatus = !is_null($userPayClockEntity) ? 1 : 0;
        $this->data['is_check_in'] = $isCheckStatus;
        return $this->pipeline();
    }

    /**
     * 获取用户打卡概览
     * @param int $uid
     * @param array $extra 必传参数信息：nickname, mobile, openId
     * @return array
     */
    public function clockUserInfo($uid, $extra = [])
    {
        $info = [
            'spend_money' => 0,
            'income_money' => 0,
            'clock_day_num' => 0
        ];
        $userEntity = PayClockUser::query()->where('uid', $uid)
            ->select(['clock_day_num', 'spend_money', 'income_money'])->first();
        if (is_null($userEntity)) {
            try {
                $this->addUserClock($uid, $extra);
            } catch (\Exception $exception) {
                $this->status = self::STATUS_FAIL;
                $this->message = $exception->getMessage();
                return $this->pipeline();
            }
        }
        $info['spend_money'] = $info['spend_money'] / 100;
        $info['income_money'] = $info['income_money'] / 100;
        $this->data['info'] = $info;
        return $this->pipeline();
    }

    private function addUserClock($uid, $extra = [])
    {
        $clockUser = [
            'spend_money' => 0,
            'income_money' => 0,
            'clock_day_num' => 0,
            'uid' => $uid,
            'source_uid' => isset($extra['openId']) ? $extra['openId'] : '',
            'nickname' => isset($extra['nickname']) ? $extra['nickname'] : '',
            'mobile' => isset($extra['mobile']) ? $extra['mobile'] : '',
            'is_remind' => 0,
            'clock_max_money' => 0,
            'clock_num' => 0,
            'long_day_num' => 0,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ];
        return PayClockUser::query()->create($clockUser);
    }

    /**
     * 用户参与次日打卡
     * @param $uid
     * @return array
     */
    public function userClock($uid)
    {
        $time = $this->systemCurrentTimestamp;
        $day = strtotime(date('Y-m-d', strtotime('-1 day', $time)));
        $start = strtotime(date('Y-m-d 05:00:00', $time));
        $end = strtotime(date('Y-m-d 08:00:00', $time));
        if ($time < $start || $time >= $end) {
            $this->status = self::STATUS_FAIL;
            $this->message = "当前时段不可以打卡哦";
            return $this->pipeline();
        }
        $clockEntity = PayClockDay::query()->where(['uid' => $uid, 'clock_day' => $day])->first();
        if (is_null($clockEntity)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您还没有参与过昨日的打卡活动";
            return $this->pipeline();
        }
        $clock = $clockEntity->toArray();
        if ($clock['clock_status'] == PayClockDay::CLOCK_STATE_IN) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您已经打过卡了";
            return $this->pipeline();
        }
        $updateTime = date('Y-m-d H:i:s', $time);
        $updateParams = [
            'clock_status' => PayClockDay::CLOCK_STATE_IN,
            'clock_time' => $updateTime,
            'update_time' => $updateTime
        ];
        $isUpdate = PayClockDay::query()->where('id', $clock['id'])->update($updateParams);
        if (!$isUpdate) {
            $this->status = self::STATUS_FAIL;
            $this->message = "当前打卡人数过多，请稍后重试";
            return $this->pipeline();
        }
        $dayAllEntity = PayClock::query()->where('clock_day', $day)->select(['id', 'clock_money'])->first();
        if (is_null($dayAllEntity)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "未获取到今日打卡的信息";
            return $this->pipeline();
        }
        $dayAll = $dayAllEntity->toArray();
        //总打卡人数加1
        PayClock::query()->where('id', $dayAll['id'])->increment('success_user_num');
        $money = empty($dayAll['clock_money']) ? 0 : $dayAll['clock_money'] / 100;
        //连续打卡天数逻辑
        $userEntity = PayClockUser::query()->where('uid', $uid)->select(['yesterday_time', 'long_day_num'])->first();
        $user = $userEntity->toArray();
        PayClockUser::query()->where('uid', $uid)->increment('clock_day_num');
        if (empty($user['yesterday_time']) ||
            date('Y-m-d', strtotime($user['yesterday_time']) + 86400) != date('Y-m-d', $time)) {
            PayClockUser::query()->where('uid', $uid)->update(['long_day_num' => 1, 'yesterday_time' => $updateTime]);
        } else {
            PayClockUser::query()->where('uid', $uid)->update(
                [
                'long_day_num' => $user['long_day_num'] + 1,
                'yesterday_time' => $updateTime]
            );
        }
        $this->data['money'] = $money;
        return $this->pipeline();
    }

    public function userRemind($uid)
    {
        $userEntity = PayClockUser::query()->where('uid', $uid)->select(['id', 'is_remind'])->first();
        if (is_null($userEntity)) {
            $this->data['is_remind'] = 0;
            return $this->pipeline();
        }
        $user = $userEntity->toArray();
        if ($user['is_remind'] == 1) {
            $this->data['is_remind'] = 1;
            return $this->pipeline();
        }
        $isUpdate = PayClockUser::query()->where('uid', $uid)->update(
            ['is_remind' => 1, 'update_time' => date('Y-m-d H:i:s')]
        );
        $this->data['is_remind'] = 0;
        if ($isUpdate) {
            $this->data['is_remind'] = 1;
        }
        return $this->pipeline();
    }

    public function userClockItem($uid, $page = 0, $limit = 10)
    {
        $offset = $page * $limit;
        $clockLogCounts = PayClockUserLog::query()->where('uid', $uid)->count();
        $this->data['total_counts'] = $clockLogCounts;
        if (empty($clockLogCounts)) {
            $this->data['items'] = [];
            return $this->pipeline();
        }
        $clockLogEntity = PayClockUserLog::query()->where('uid', $uid)->offset($offset)->limit($limit)
            ->select(['clock_day', 'money_type', 'change_money', 'create_time'])
            ->orderBy('id', 'desc')->get();
        $clockLogs = $clockLogEntity->toArray();
        array_walk($clockLogs, function (&$logs) {
            $logs = (array)$logs;
            $logs['clock_day'] = date('Y-m-d H:i', strtotime($logs['create_time']));
            $logs['money'] = $logs['change_money'] / 100;
            $logs['title'] = PayClockUserLog::$type[$logs['money_type']];
        });
        $this->data['items'] = $clockLogs;
        return $this->pipeline();
    }

    /**
     * 昨日参与记录
     * @param $uid
     * @return array
     */
    public function yesterdayClock($uid)
    {
        $yesterdayInfo = [
            'clock_status' => PayClockDay::CLOCK_STATE_INIT
        ];
        $day = strtotime(date('Y-m-d', strtotime('-1 day', $this->systemCurrentTimestamp)));
        $clockDayEntity = PayClockDay::query()->where(['uid' => $uid, 'clock_day' => $day])
            ->select(['id', 'clock_status'])->first();
        if (!is_null($clockDayEntity)) {
            $clockDay = $clockDayEntity->toArray();
            $yesterdayInfo['clock_status'] = $clockDay['clock_status'];
        }
        $this->data['yesterday'] = $yesterdayInfo;
        return $this->pipeline();
    }

    /**
     * @param $uid
     * @param array $extra 参数信息：mobile , nickname , openId
     * @return array
     */
    public function generateOrder($uid, $extra = [])
    {
        $clockDayTimestamp = strtotime(date('Y-m-d', $this->systemCurrentTimestamp));
        // 查询昨天是否参与
        $isYesterdayClock = PayClockOrder::query()
            ->where(['user_id' => $uid, 'clock_day' => $clockDayTimestamp - 86400])->first();
        // 昨天参与过才限制
        if ($isYesterdayClock) {
            $checkTime = strtotime(date('Y-m-d 08:05:00', $this->systemCurrentTimestamp));
            if (time() < $checkTime) {
                $this->status = self::STATUS_FAIL;
                $this->message = "八点五分以后才可以参与活动哦";
                return $this->pipeline();
            }
        }
        // 验证用户是否有今日成功的订单
        $existOrder = PayClockOrder::query()
            ->where(['user_id' => $uid, 'clock_day' => $clockDayTimestamp, 'order_status' => PayClockOrder::PAY_ON])
            ->select(['id'])->first();
        if (!is_null($existOrder)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您今天已经参与过了，不要重复支付哦";
            return $this->pipeline();
        }
        if (!isset($extra['mobile'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您还没有授权手机号，为了更好的通知到您，请去先授权后参与哦";
            return $this->pipeline();
        }
        if (!isset($extra['nickname'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您还没有授权昵称信息，请去先授权后参与哦";
            return $this->pipeline();
        }
        if (!isset($extra['openId'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您还没有授权微信平台ID信息，请去先授权后参与哦";
            return $this->pipeline();
        }
        $orderNo = $this->createOrderNo();
        $time = date('Y-m-d H:i:s');
        // 从配置信息中读取参与金额
        $money = empty(Config::get('clock.participate_clock_money')) ? 1 : Config::get('clock.participate_clock_money');
        // todo 未设置折扣金额
        $orderInfo = [
            'order_no' => $orderNo,
            'clock_day' => $clockDayTimestamp,
            'user_id' => $uid,
            'buyer_id' => $extra['openId'],
            'order_status' => PayClockOrder::PAY_WAIT,
            'pay_amount' => $money * 100, // 每天参与活动下单,参与费为一元，单位:分
            'discount_amount' => 0,
            'create_time' => $time,
            'update_time' => $time,
        ];
        /** @var PayClockOrder $orderEntity */
        $orderEntity = PayClockOrder::query()->create($orderInfo);
        if (is_null($orderEntity)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "订单服务失败";
            return $this->pipeline();
        }
        $order = $orderEntity->toArray();
        $this->data['order_id'] = $order['id'];
        $this->data['order_no'] = $orderNo;
        return $this->pipeline();
    }

    private function createOrderNo()
    {
        $now = str_replace(time(), '', microtime());
        $now = trim(str_replace('0.', '', $now));
        $now = rand(1000, 9999) . substr($now, 3, 3);
        return Config::get('clock.order_prefix') . date('Ymd') . $now;
    }

    /**
     * 支付回调通知
     * @param $orderNo
     * @param array $extra, 必须含有 amount(回调金额), buyerId(支付用户openid或source_uid), transactionId(渠道订单号)
     *                      notify_time(回调通知时间), content(回调内容数据块), nickname(昵称), mobile(手机号)
     * @return array
     */
    public function notifyPayInfo($orderNo, $extra = [])
    {
        $orderEntity = PayClockOrder::query()->where('order_no', $orderNo)->first();
        if (is_null($orderEntity)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "订单号不存在，order_id: " . $orderNo;
            return $this->pipeline();
        }
        // 订单存入缓存，保证在支付回调过程中只会进入一次，避免重复同步
        $orderCacheKey = Config::get('clock.order_notify_cache_prefix') . $orderNo;
        if (!Cache::has($orderCacheKey)) {
            $cacheExpire = 108000;
            Cache::add($orderCacheKey, $orderNo, $cacheExpire);
            // todo 支付成功了发送模版消息
        }
        $order = $orderEntity->toArray();
        // 校验订单是否已支付，若已支付了，将正确返回，不做任何处理
        if ($order['order_status'] == PayClockOrder::PAY_ON) {
            $this->status = self::STATUS_SUCCESS;
            $this->message = "支付回调中，订单已支付";
            return $this->pipeline();
        }
        if (!isset($extra['amount'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "未获取支付回调的金额";
            return $this->pipeline();
        }
        if ($extra['amount'] != $order['pay_amount'] / 100) {
            $this->status = self::STATUS_FAIL;
            $this->message = "支付回调中，支付金额不一致";
            return $this->pipeline();
        }
        if (!isset($extra['transactionId'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "未获取支付回调的渠道订单号信息";
            return $this->pipeline();
        }
        if (!isset($extra['buyerId'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "未获取支付回调的支付用户信息";
            return $this->pipeline();
        }
        if ($extra['buyerId'] != $order['buyer_id']) {
            $this->status = self::STATUS_FAIL;
            $this->message = "支付回调中，支付用户不一致";
            return $this->pipeline();
        }
        $clockEntity = PayClock::query()->where('clock_day', $order['clock_day'])->select(['id'])->first();
        if (is_null($clockEntity)) {
            $clockParams = [
                'clock_day' => $order['clock_day'],
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            PayClock::query()->insert($clockParams);
        }
        try {
            DB::beginTransaction();
            $updateParams = [
                'order_status' => PayClockOrder::PAY_ON,
                'pay_time' => isset($extra['notify_time']) ? $extra['notify_time'] : date('Y-m-d H:i:s'),
                'pay_result' => isset($extra['content']) ? $extra['content'] : '',
                'transaction_id' => $extra['transactionId'], // 渠道支付订单ID
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $isUpdateResult = PayClockOrder::query()->where('id', $order['id'])->update($updateParams);
            if (!$isUpdateResult) {
                $message = sprintf("订单号：%s, 更新订单表失败, 更新内容：%s", $order['id'], json_encode($updateParams, JSON_UNESCAPED_UNICODE));
                throw new \Exception($message);
            }

            // 更新总表,参与人数加1,奖金池加n元
            $clockEntity = PayClock::query()->where('clock_day', $order['clock_day'])->first();
            $clock = $clockEntity->toArray();
            $isUpdateClock = PayClock::query()->where('clock_day', $order['clock_day'])->update([
                'user_num' => $clock['user_num'] + 1,
                'clock_money' => $clock['clock_money'] + $order['pay_amount'],
                'update_time' => date('Y-m-d H:i:s')
            ]);
            if (!$isUpdateClock) {
                $message = sprintf("订单号：%s, 更新总表参与人数(user_num) 和 参与金额(clock_money)失败", $order['id']);
                throw new \Exception($message);
            }
            if (!isset($extra['nickname'])) {
                throw new \Exception('未获取用户的昵称信息');
            }
            if (!isset($extra['mobile'])) {
                throw new \Exception('未获取用户的手机信息');
            }
            $clockDayInfo = [
                'uid' => $order['user_id'],
                'nickname' => $extra['nickname'],
                'mobile' => $extra['mobile'],
                'clock_day' => $order['clock_day'],
                'order_no' => $orderNo,
                'order_money' => $order['pay_amount'],
                'discount_money' => $order['discount_money'],
                'clock_status' => PayClockDay::CLOCK_STATE_OUT,
                'source_uid' => $order['buyer_id'],
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $isInsertClockDay = PayClockDay::query()->insert($clockDayInfo);
            if (!$isInsertClockDay) {
                throw new \Exception('新增每日参与表失败, 更新信息:' . json_encode($clockDayInfo, JSON_UNESCAPED_UNICODE));
            }

            // 记录用个人数据,个人支出加n元，参加次数加1
            $clockUserEntity = PayClockUser::query()->where('uid', $order['user_id'])->first();
            if (is_null($clockUserEntity)) {
                throw new \Exception("未获取到用户打卡信息，UID:" . $order['user_id']);
            }
            $clockUser = $clockUserEntity->toArray();
            $isUpdateUser = PayClockUser::query()->where('uid', $order['user_id'])->update([
                'clock_num' => $clockUser['clock_num'] + 1,
                'spend_money' => $clockUser['spend_money'] + $order['pay_amount'],
                'mobile' => $extra['mobile'],
                'nickname' => $extra['nickname']
            ]);
            if (!$isUpdateUser) {
                throw new \Exception('更新用户的参与数量、打卡花费、手机信息和昵称信息失败！');
            }
            //记录个人金额变动日志
            $content = sprintf(
                "用户ID：%s 参与 %s 打卡活动花费 %s 元",
                $order['user_id'],
                date('Y-m-d', $order['clock_day']),
                $order['pay_amount'] / 100
            );
            $userInsertLog = [
                'uid' => $order['user_id'],
                'clock_day' => $order['clock_day'],
                'money_type' => PayClockUserLog::CLOCK_CHALLENGE,
                'change_money' => $order['pay_amount'],
                'log_content' => $content,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $isLog = PayClockUserLog::query()->insert($userInsertLog);
            if (!$isLog) {
                $message = sprintf("新增用户个人金额日志表失败, 日志内容：%s", json_encode($userInsertLog, JSON_UNESCAPED_UNICODE));
                throw new \Exception($message);
            }
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::channel(Config::get('clock.log_file'))->error($exception->getMessage());
            $this->status = self::STATUS_FAIL;
            $this->message = $exception->getMessage();
        }
        return $this->pipeline();
    }

    /**
     * @param int $uid
     * @param \Closure $handler
     * @return array
     */
    public function userDrawRed($uid, $handler)
    {
        $day = strtotime(date('Y-m-d 08:00:00', $this->systemCurrentTimestamp));
        // 时间判断，开奖时间在当天八点后
        if ($this->systemCurrentTimestamp <= $day) {
            $this->status = self::STATUS_FAIL;
            $this->message = "当前还未到开奖时间";
            return $this->pipeline();
        }
        if ($this->systemCurrentTimestamp < strtotime(date('Y-m-d 08:05:00', $this->systemCurrentTimestamp))) {
            $this->status = self::STATUS_FAIL;
            $this->message = "红包计算中，请稍后尝试";
            return $this->pipeline();
        }
        // 加锁判断
        $isLock = $this->setRedLock($uid);
        if (!$isLock) {
            $this->status = self::STATUS_FAIL;
            $this->message = "请不要频繁操作";
            return $this->pipeline();
        }
        $clockDay = strtotime(Carbon::yesterday()->format('Y-m-d'));
        $userPayClockDayEntity = PayClockDay::query()->where(['uid' => $uid, 'clock_day' => $clockDay])
            ->first();
        if (is_null($userPayClockDayEntity)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您没有参加昨日打卡活动哦";
            return $this->pipeline();
        }
        $userClockDay = $userPayClockDayEntity->toArray();
        if ($userClockDay['clock_status'] != PayClockDay::CLOCK_STATE_IN) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您今天没有打卡成功哦";
            return $this->pipeline();
        }
        //已经发过红包
        if (!empty($userClockDay['clock_money']) || $userClockDay['red_status'] == PayClockDay::RED_RELEASE_ON) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您已经领取过红包了";
            return $this->pipeline();
        }
        // 发放红包
        $assignResult = $this->assignUserRedMoney($userClockDay, $handler);
        if (empty($assignResult['status'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = $assignResult['message'];
            return $this->pipeline();
        }
        if (isset($assignResult['data']['error'])) {
            // 插入错误日志
            $this->addClockErrorLog($assignResult['data']['error']);
        }
        return $this->pipeline();
    }

    /**
     * 增加用户领取红包的错误日志，包括打款成功记录更新失败、打款失败等日志信息
     * @param array $content
     * @return bool
     */
    public function addClockErrorLog($content = [])
    {
        $data = [
            'clock_day_id' => empty($content['clock']['id']) ? 0 : $content['clock']['id'],
            'clock_day' => empty($content['clock']['clock_day']) ? 0 : $content['clock']['clock_day'],
            'red_money'=> empty($content['data']['clock_money']) ? '0.00' : $content['data']['clock_money'],
            'order_no' => empty($content['data']['order_no']) ? 0 : $content['data']['order_no'],
            'uid' => empty($content['clock']['uid']) ? 0 : $content['clock']['uid'],
            'error_msg' => empty($content['msg']) ? 0 : $content['msg'],
            'pay_result' => empty($content['data']['pay_result']) ? 0 : $content['data']['pay_result'],
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ];
        if (empty($data['clock_day_id']) || empty($data['clock_day']) || empty($data['order_no']) || empty($data['uid'])) {
            return false;
        }
        $isInsertResult = PayClockUserErrorLog::query()->insert($data);
        if (!$isInsertResult) {
            Log::channel(Config::get('clock.log_file'))->warning("打款成功记录更新失败数据", $data);
        }
        return true;
    }

    private function setRedLock($uid)
    {
        $key = Config::get('clock.red_lock_prefix_key') . $uid;
        if (Cache::get($key)) {
            return false;
        }
        Cache::add($key, 1, 5);
        return true;
    }

    /**
     * 根据打卡记录发放红包
     * @param array $clock
     * @param \Closure $handler
     * @return array
     */
    public function assignUserRedMoney($clock, $handler)
    {
        if (empty($clock)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "分配红包的参数校验失败";
            return $this->pipeline();
        }
        if (empty($clock['clock_day'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "分配红包的打卡日期校验失败";
            return $this->pipeline();
        }
        if (empty($clock['uid'])) {
            $this->status = self::STATUS_FAIL;
            $this->message = "分配红包的用户信息校验失败";
            return $this->pipeline();
        }
        // 用户今日已经发放过
        $isDrawKey = $this->getUserDayRedLockKey($clock['uid'], $clock['clock_day']);
        if (Cache::has($isDrawKey)) {
            $this->status = self::STATUS_FAIL;
            $this->message = "您今天已经领过红包了哦";
            return $this->pipeline();
        }
        // 奖金池可以红包总金额, 每次发放成功后，红包的缓存金额将减少
        $todayMoney = $this->getTodayMaxMoney($clock['clock_day']);
        // 当今日红包总金额小于1分，将不进行发放
        if ($todayMoney < 1) {
            $this->status = self::STATUS_FAIL;
            $this->message = "今天的红包已经发放完毕,剩余红包次日主动发放";
            return $this->pipeline();
        }
        $initClockMoney = Config::get('clock.participate_clock_money');
        $initClockMoney = empty($initClockMoney) ? 1 : $initClockMoney; // 单位是元

        // 随机红包金额 已通过定时任务将红包已拆分放入缓存中, 单位：分
        $redRandMoney = $this->getCacheUserRedMoney($clock['clock_day'], $clock['uid']);
        // 红包金额每次都需要与剩余红包总额进行校验，当红包金额小于0时，将红包金额设置为0
        $redMoney = ($redRandMoney + $initClockMoney * 100) / 100;
        // 获取剩余红包金额，单位：分
        $redRemainMoney = $this->getDayRemainRedMoney($clock['clock_day'], $redRandMoney);
        if ($redRemainMoney < 0) {
            $redMoney = $initClockMoney;
        }
        Log::channel(Config::get('clock.log_file'))->error(sprintf("初始化红包数据信息：todayMoney: %s, redRandMoney: %s, redRemainMoney: %s, 红包金额：%s",
            $todayMoney, $redRandMoney, $redRemainMoney, $redMoney));
//        if ($redRemainMoney < 1) {
//            $redMoney = $initClockMoney;
//        } else {
//            $redMoney = ($redRandMoney + $initClockMoney * 100) / 100;
//        }
        if ($redMoney * 100 > $todayMoney) {
            //余额不够大于一块钱变为默认红包，随机红包为0
            if ($todayMoney >= 100 * $initClockMoney) {
                $redMoney = $initClockMoney;
                $redRandMoney = 0;
            } else {
                $this->status = self::STATUS_FAIL;
                $this->message = "今天的红包已经发放完毕,剩余红包次日主动发放";
                return $this->pipeline();
            }
        }
        // todo 通过渠道(支付宝、微信)发放红包金额出去
        $orderNo = $this->createOrderNo();
        // todo start 关闭渠道发放红包，用于包测试
        $result = call_user_func_array($handler, [$orderNo, $clock['uid']]);
        Log::channel(Config::get('clock.log_file'))->info("Channel transfer result", $result);
        if (!$result) {
            // 发送失败还回库存金额
            $key = $this->getRedMoneyKey($clock['clock_day']);
            Cache::increment($key, $redRandMoney);
            Log::channel(Config::get('clock.log_file'))->error(sprintf("红包发放失败, 金额：{$redMoney}"), $clock);

            $this->status = self::STATUS_FAIL;
            $this->message = "您的支付宝账号可能存在问题，系统会在24小时之内自动打款到您的账户，请注意查收，如到期仍未收到，请联系客服处理";
            return $this->pipeline();
        }

        // todo end 关闭渠道发放红包，用于包测试
        $money = $redMoney * 100;
        // 红包已发放，记录缓存标示
        Cache::put($isDrawKey, 1, 7*24*3600);
        // 更新缓存总奖金
        $dayMoneyKey = $this->getDayMoneyKey($clock['clock_day']);
        Cache::decrement($dayMoneyKey, $money);
        $this->data['core'] = [
            'money' => $redMoney
        ];
        $updateClockDayParams = [
            'order_no' => $orderNo,
            'clock_money' => $money,
            'red_status' => PayClockDay::RED_RELEASE_ON,
            'pay_result' => json_encode($result),
            'red_time' => date('Y-m-d H:i:s')
        ];
        try {
            DB::beginTransaction();
            $isUpdateClockDay = PayClockDay::query()->where('id', $clock['id'])->update($updateClockDayParams);
            if (!$isUpdateClockDay) {
                throw new \Exception("红包发放成功，更新用户记录失败 clock_id : 
                {$clock['id']}, updateClockDayParams:" . json_encode($updateClockDayParams, JSON_UNESCAPED_UNICODE));
            }
            $userEntity = PayClockUser::query()->where('uid', $clock['uid'])->select(['id', 'income_money', 'clock_max_money'])->first();
            if (is_null($userEntity)) {
                throw new \Exception("红包发放成功，未获取到用户信息");
            }
            $isUpdateUser = PayClockUser::query()->where('uid', $clock['uid'])->increment('income_money', $money);
            if (!$isUpdateUser) {
                throw new \Exception("红包发放成功，更新用户记录总表失败, uid: {$clock['uid']}, income_money: {$money}, order_no: {$orderNo}");
            }
            $user = $userEntity->toArray();
            if ($user['clock_max_money'] < $money) {
                $isUpdateUser = PayClockUser::query()->where('uid', $user['id'])->update([
                    'clock_max_money' => $money,
                    'update_time' => date('Y-m-d H:i:s')
                ]);
                if (!$isUpdateUser) {
                    throw new \Exception( "红包发放成功，更新用户记录总表失败, uid: {$clock['uid']}, clock_max_money: {$money}, order_no: {$orderNo}");
                }
            }
            // 记录个人金额变动日志
            $content = sprintf("用户ID: %s 参与 %s 打卡活动红包发放：%s 元", $clock['uid'], date('Y-m-d', $clock['clock_day']), $redMoney);
            $userLogParams = [
                'uid' => $clock['uid'],
                'clock_day' => $clock['clock_day'],
                'money_type' => PayClockUserLog::CLOCK_RED,
                'change_money' => $money,
                'log_content' => $content,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $isInsertLog = PayClockUserLog::query()->insert($userLogParams);
            if (!$isInsertLog) {
                // todo 增加日志
                throw new \Exception("红包发放成功，插入用户记录日志表失败, content:" . json_encode($userLogParams, JSON_UNESCAPED_UNICODE));
            }
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::channel(Config::get('clock.log_file'))->error($exception->getMessage());
            $this->data['error'] = [
                'clock' => $clock,
                'data' => $updateClockDayParams,
                'msg' => $exception->getMessage(),
            ];
            $this->message = $exception->getMessage();
        }

        return $this->pipeline();


    }

    /**
     * 获取剩余红包总金额
     * @param int $clockDay
     * @param int $redMoney 单位:分
     * @return int 单位：分
     */
    public function getDayRemainRedMoney($clockDay, $redMoney)
    {
        if (empty($clockDay)) {
            return 0;
        }
        $key = $this->getRedMoneyKey($clockDay);
        if (Cache::has($key)) {
            if (Cache::get($key) < $redMoney) {
                return -1;
            }
            Cache::decrement($key, $redMoney);

            return Cache::get($key);
        }
        // ClockRedInit服务中已将红包总额缓存，若未取到缓存内容，将重新计算
        $remainMoney = $this->initReleaseRedFee($clockDay);
        Cache::add($key, $remainMoney * 100, 7*24*3600);
        if ($remainMoney * 100 < $redMoney) {
            return -1;
        }
        Cache::decrement($key, $redMoney);
        return Cache::get($key);
    }

    /**
     * 首次在系统定时任务中初始化可发放的红包总额
     * 再次初始化可发放的红包总额
     * @param $clockDay
     * @return float|int
     */
    private function initReleaseRedFee($clockDay)
    {
        // 计算红包金额
        $clockEntity = PayClock::query()->where('clock_day', $clockDay)->select(['clock_money', 'success_user_num'])
            ->first();
        if (is_null($clockEntity)) {
            return 0;
        }
        $initMoney = empty(Config::get('clock.participate_clock_money')) ? 1 : Config::get('clock.participate_clock_money');
        $clock = $clockEntity->toArray();
        $userSuccessClocks = PayClockDay::query()->where([
            'clock_status' => PayClockDay::CLOCK_STATE_IN,
            'clock_day' => $clockDay,
            'red_status' => PayClockDay::RED_RELEASE_OFF])->count();
        // 不一致，按详情的分
        if ($clock['success_user_num'] != $userSuccessClocks) {
            $successMoney = PayClockDay::query()->where(['clock_status' => PayClockDay::CLOCK_STATE_IN,
                'clock_day' => $clockDay, 'red_status' => PayClockDay::RED_RELEASE_ON])->sum('clock_money');
            $remain = ($clock['clock_money'] - $successMoney) / 100;
            $rewardUserCounts = $userSuccessClocks;
        } else {
            $remain = $clock['clock_money'] / 100;
            $rewardUserCounts = $clock['success_user_num'];
        }
        $remainReleaseMoney = $remain - $rewardUserCounts * $initMoney; // 瓜分红包总数 = 参与报名总金额 - 已打卡人数 * 打卡金额
        // 扣除服务费
        $remainReleaseMoney = $remainReleaseMoney * (1 - Config::get('clock.red_service_ratio'));
        return $remainReleaseMoney;
    }

    /**
     * 从定时任务中获取用户的缓存红包金额
     * @param $clockDay
     * @param $uid
     * @return float|int
     */
    private function getCacheUserRedMoney($clockDay, $uid)
    {
        if (empty($clockDay)) {
            return 0;
        }
        $key = $this->getUserClockRewardKey($clockDay, $uid);
        $money = Cache::get($key);
        $money = $money > 98 ? 98 : $money;

        return $money < 0.01 ? 0 : $money * 100;
    }


    /**
     * 每天8点终止用户打卡入口，定时任务计算用户红包金额
     * @param $clockDay
     * @return array
     */
    public function initUserClockRed($clockDay)
    {
        $this->data['red_items'] = [];
        $clockEntity = PayClock::query()->where('clock_day', $clockDay)->first();
        if (is_null($clockEntity)) {
            $this->message = sprintf("clock_day: %s, 执行结果：无打卡记录", $clockDay);
            return $this->pipeline();
        }
        $clock = $clockEntity->toArray();
        $userSuccessClocks = PayClockDay::query()->where([
            'clock_status' => PayClockDay::CLOCK_STATE_IN,
            'clock_day' => $clockDay,
            'red_status' => PayClockDay::RED_RELEASE_OFF])->count();
        // 不一致，按详情的分
        if ($clock['success_user_num'] != $userSuccessClocks) {
            $successMoney = PayClockDay::query()->where(['clock_status' => PayClockDay::CLOCK_STATE_IN,
                'clock_day' => $clockDay, 'red_status' => PayClockDay::RED_RELEASE_ON])->sum('clock_money');
            $remain = ($clock['clock_money'] - $successMoney) / 100;
            $rewardUserCounts = $userSuccessClocks;
        } else {
            $remain = $clock['clock_money'] / 100;
            $rewardUserCounts = $clock['success_user_num'];
        }
        // 获取打卡日的红包总金额(参与活动当日总奖池金额)
        $todayTotalMoneyKey = $this->getDayMoneyKey($clockDay);
        Cache::add($todayTotalMoneyKey, $remain * 100, 7*24*3600);
        $initClockMoney = empty(Config::get('clock.participate_clock_money')) ? 1 : Config::get('clock.participate_clock_money');
        $redTotalMoney = $remain - $rewardUserCounts * $initClockMoney; // 瓜分红包总数 = 参与报名总金额 - 已打卡人数 * 打卡金额

        // todo 从红包中预留做服务费, 剩余的用来发红包
        $redTotalMoney = $redTotalMoney * (1 - Config::get('clock.red_service_ratio')); // 单位：元
        $logInfo = sprintf(
            "基础红包金额(initClockMoney):%s 元; 参与打卡报名总金额(remain):%s 元; 发放人数：%s; 奖金总数(redTotalMoney): %s 元",
            $initClockMoney,
            $remain,
            $rewardUserCounts,
            $redTotalMoney
        );
        Log::channel(Config::get('clock.log_file'))->info($logInfo);

        if ($redTotalMoney <= 0) {
            $this->message = "参与报名人数与成功打卡人数相同，都发保底";
            return $this->pipeline();
        }

        $todayRedReleaseKey = $this->getRedMoneyKey($clockDay);
        Cache::add($todayRedReleaseKey, $redTotalMoney * 100, 7*24*3600);
        // 当红包金额小于1元，将都发保底，例子：若5个参与活动，4个人打卡，剩余的奖金为1元，这1元将不瓜分
        if ($redTotalMoney < 1) {
            $this->message = "奖金不够分，都发保底";
            return $this->pipeline();
        }

        // Step3 获取系统配置当前最大额度红包配置
        $setting = $this->redSetting();
        $maxLuckyUsers = $setting['num']; // 大额红包人数
        $maxLuckyRedMoney = $redTotalMoney * $setting['proportion']; // 大额红包总金额

        $normalRewardUsers = $rewardUserCounts - $maxLuckyUsers; //普通人数
        $normalTotalRedMoney = $redTotalMoney - $maxLuckyRedMoney; // 小额红包总数
        $logInfo = sprintf("基础红包金额(initClockMoney):%s 元; 参与打卡报名总金额(remain):%s 元; 发放人数：%s 位; 奖金总数(redTotalMoney): %s 元; 普通红包人数:%s 位; 小额红包总数:%s 元; 大额人数: %s 位; 大额红包总额: %s 元",
            $initClockMoney,
            $remain,
            $rewardUserCounts,
            $redTotalMoney,
            $normalRewardUsers,
            $normalTotalRedMoney,
            $maxLuckyUsers,
            $maxLuckyRedMoney
        );
        Log::channel(Config::get('clock.log_file'))->info($logInfo);

        // Step4
        $normalRedMoneyItem = [$normalTotalRedMoney];
        // 当活动打卡人数参与人数 = 大额红包人数时，不会存在普通红包参与人数
        if ($normalRewardUsers > 0 ) {
            $money = round($normalTotalRedMoney / $normalRewardUsers, 2);
            if ($money < 0.01) {
                $this->message = "普通人数领取的奖金不够发，都发保底";
                return $this->pipeline();
            }
            $normalRedMoneyItem = $this->sendRandBonus($normalTotalRedMoney, $normalRewardUsers); // 普通打卡金额结果
        }

        $luckyRedMoneyItem = $this->sendRandBonus($maxLuckyRedMoney, $maxLuckyUsers); // 幸运打卡金额结果
        if ($redTotalMoney < (array_sum($normalRedMoneyItem) + array_sum($luckyRedMoneyItem))) {
            // 计算超出总金额上限，减少大额红包
            $maxLuckyRedMoney = $redTotalMoney - array_sum($normalRedMoneyItem);
            $luckyRedMoneyItem = $this->sendRandBonus($maxLuckyRedMoney, $maxLuckyUsers);
        }
        for ($i = 0; $i < $maxLuckyUsers; $i++) {
            array_push($normalRedMoneyItem, array_pop($luckyRedMoneyItem));
        }
        $this->data['red_items'] = $normalRedMoneyItem;
        return $this->pipeline();
    }

    /**
     * 系统运营红包的配置关系
     * @return array
     */
    public function redSetting()
    {
        $currentTimeFormat = date('Y-m-d H:i:s');
        $settingEntity = PayClockSetting::query()->where('start_time', "elt", $currentTimeFormat)
            ->where('end_time', 'egt', $currentTimeFormat)
            ->select(['id', 'money_proportion', 'red_num'])->first();
        $redInitProportion = Config::get('clock.red_proportion');
        if (!is_null($settingEntity)) {
            $setting = $settingEntity->toArray();
            $redInitProportion['num'] = $setting['red_num'];
            $redInitProportion['proportion'] = $setting['money_proportion'] / 100;
        }
        return $redInitProportion;
    }

    public function getTopRecord($page = 0, $limit = 10)
    {
        $offset = $page * $limit;
        $tops = [
            'total_counts' => 0,
            'items' => []
        ];
        $recordQueryBuilder = PayClockUser::query()->where('mobile', '!=', '');
        $tops['total_counts'] = $recordQueryBuilder->count('*');
        if (empty($tops['total_counts'])) {
            $this->data['records'] = $tops;
            return $this->pipeline();
        }
        $recordEntities = PayClockUser::query()->where('mobile', '!=', '')
            ->limit($limit)->offset($offset)->select(['mobile', 'clock_day_num as clockDayCount', 'income_money as incomeMoney'])
            ->orderBy('income_money', 'desc')->get();
        if (is_null($recordEntities)) {
            $this->data['talent'] = $tops;
            return $this->pipeline();
        }
        $recordItem = $recordEntities->toArray();
        array_walk($recordItem, function (&$item) {
            $item['mobile'] = $this->saltTel($item['mobile']);
        });
        $tops['items'] = $recordItem;
        $this->data['records'] = $tops;
        return $this->pipeline();
    }

    /**
     * 获取打卡达人信息列表
     * @return array|mixed
     */
    public function getTopUser()
    {
        $time = date('Y-m-d', $this->systemCurrentTimestamp);
        $today = strtotime($time);
        $topKey = Config::get('clock.prefix_top_key') . $today;
        if (Cache::has($topKey)) {
            $this->data['talent'] = Cache::get($topKey);
            return $this->pipeline();
        }
        $yesterday = strtotime(date('Y-m-d', $this->systemCurrentTimestamp - self::DAY_TIMESTAMP));
        $topInfo = [
            'persistent' => ['type' => 1, 'uid' => '', 'icon' => '', 'nickname' => '', 'desc' => '虚席以待'],
            'lucky' => ['type' => 2, 'uid' => '', 'icon' => '', 'nickname' => '', 'desc' => '虚席以待'],
            'early' => ['type' => 3, 'uid' => '', 'icon' => '', 'nickname' => '', 'desc' => '虚席以待'],
        ];
        // todo 毅力达人
        $persistentTopEntity = PayClockUser::query()->where('long_day_num', '>', 0)
            ->orderBy('long_day_num', 'desc')->orderBy('update_time', 'desc')
            ->select(['uid', 'long_day_num', 'nickname', 'mobile'])->first();
        if (!is_null($persistentTopEntity)) {
            $persistentTop = $persistentTopEntity->toArray();
            $topInfo['persistent']['uid'] = $persistentTop['uid'];
            $topInfo['persistent']['nickname'] = $persistentTop['nickname'];
            $topInfo['persistent']['desc'] = sprintf('连续打卡%s次', $persistentTop['long_day_num']);
        }
        // todo 运气达人
        $luckyTopKey = $this->topUserClockKey($yesterday);
        if (Cache::has($luckyTopKey)) {
            $cacheLuckyTopItem = Cache::get($luckyTopKey);
            $cacheLuckyTopItem = json_decode($cacheLuckyTopItem, true);
            $topInfo['lucky']['uid'] = $cacheLuckyTopItem['uid'];
            $topInfo['lucky']['nickname'] = isset($cacheLuckyTopItem['nickname']) && $cacheLuckyTopItem['nickname'] ? $cacheLuckyTopItem['nickname'] : '';
            $topInfo['lucky']['mobile'] = isset($cacheLuckyTopItem['mobile']) && $cacheLuckyTopItem['mobile'] ?
                $this->saltTel($cacheLuckyTopItem['mobile']) : '';
            $topInfo['lucky']['desc'] = sprintf('分到了%s元', $cacheLuckyTopItem['money']);
        } else {
            $luckyTopEntity = PayClockDay::query()->where('clock_status', PayClockDay::CLOCK_STATE_IN)
                ->where('red_status', PayClockDay::RED_RELEASE_ON)->where('clock_day', $yesterday)
                ->select(['uid', 'clock_money', 'nickname', 'mobile'])
                ->orderBy('clock_money', 'desc')->first();
            if (!is_null($luckyTopEntity)) {
                $luckyTop = $luckyTopEntity->toArray();
                $topInfo['lucky']['uid'] = $luckyTop['uid'];
                $topInfo['lucky']['nickname'] = isset($luckyTop['nickname']) && $luckyTop['nickname'] ? $luckyTop['nickname'] : '';
                $topInfo['lucky']['mobile'] = isset($luckyTop['mobile']) && $luckyTop['mobile'] ?
                    $this->saltTel($luckyTop['mobile']) : '';
                $topInfo['lucky']['desc'] = sprintf('分到了%s元', $luckyTop['clock_money'] / 100);
            }
        }
        // todo 早起达人
        $earlyTopEntity = PayClockDay::query()->where('clock_status', PayClockDay::CLOCK_STATE_IN)
            ->where('clock_day', $yesterday)->orderBy('clock_time', 'asc')
            ->select(['uid', 'clock_time', 'nickname', 'mobile'])->first();
        if (!is_null($earlyTopEntity)) {
            $earlyTop = $earlyTopEntity->toArray();
            $topInfo['early']['uid'] = $earlyTop['uid'];
            $topInfo['early']['nickname'] = isset($earlyTop['nickname']) && $earlyTop['nickname'] ? $earlyTop['nickname'] : '';
            $topInfo['early']['mobile'] = isset($earlyTop['mobile']) && $earlyTop['mobile'] ? $this->saltTel($earlyTop['mobile']) : '';
            $topInfo['early']['desc'] = sprintf('%s打卡', date('H:i:s', strtotime($earlyTop['clock_time'])));
        }
        Cache::add($topKey, array_values($topInfo), 10 * 60);

        $this->data['talent'] = $topInfo;
        return $this->pipeline();

    }

    /**
     * 手机号处理
     * @param $telephone
     * @return string
     */
    public function saltTel($telephone)
    {
        return substr($telephone, 0, 3) . ' *** ' . substr($telephone, -4);
    }

    /**
     * 指定天参与打卡中最多可发放金额 单位：分
     * @param $clockDay
     * @return int|mixed
     */
    public function getTodayMaxMoney($clockDay)
    {
        $key = $this->getDayMoneyKey($clockDay);
        if (!Cache::has($key)) {
            // 计算红包金额
            $money = 0;
            $clockEntity = PayClock::query()->where('clock_day', $clockDay)->select(['clock_money'])->first();
            if (!is_null($clockEntity)) {
                $clock = $clockEntity->toArray();
                $money = empty($clock['clock_money']) ? 0 : $clock['clock_money'];
                Cache::add($key, $money, 7 * 24 * 3600);
            }
            return $money;
        }
        return Cache::get($key);
    }


    public function getUserClockRewardKey($clockDay, $uid)
    {
        return sprintf("clock_on_%s_uid_%s_red_money", $clockDay, $uid);
    }

    /**
     * 指定日期下打卡用户中幸运榜
     * @param $clockDay
     * @return string
     */
    public function topUserClockKey($clockDay)
    {
        return sprintf("clock_on_%s_top", $clockDay);
    }

    /**
     * 随机红包 分奖的核心，根据需要奖金总额和奖金人数，随机产出红包
     * @param int $total 总金额
     * @param int $count 红包数量
     * @param int $type 1 随机 2均分
     * @return array
     */
    private function sendRandBonus($total = 0, $count = 3, $type = 1)
    {
        $max = 98;
        if ($type == 1) {
            $items = [];
            $input = range(0.01, $total, 0.01);
            if ($count > 1) {
                $rand_keys = (array) array_rand($input, $count-1);
                $last = 0;
                foreach ($rand_keys as $i => $key) {
                    $current = $input[$key] - $last;
                    $items[] = round($current, 2) > $max ? $max : round($current, 2);
                    $last = $input[$key];
                }
            }
            $items[] = round($total - array_sum($items), 2) > $max ? $max : round($total- array_sum($items), 2);
        } else {
            $avg = number_format($total / $count, 2);
            $i = 0;
            $items = [];
            while ($i < $count) {
                $money = $i < $count-1 ? $avg: ($total - array_sum($items));
                $items[] = round($money, 2) > $max ? $max : round($money, 2);
                $i++;
            }
        }
        return $items;
    }

    private function getUserDayRedLockKey($uid, $day)
    {
        return sprintf("user_red_is_send_day_%s_uid_%s", $day, $uid);
    }

    private function getDayMoneyKey($day)
    {
        return sprintf("today_red_money_max_%s", $day);
    }

    /**
     * 红包随机奖金池总金额key
     * @param $day
     * @return string
     */
    private function getRedMoneyKey($day)
    {
        return sprintf("today_release_red_money_%s", $day);
    }
}
