<?php


namespace Happy\Clock\Commands;


use Carbon\Carbon;
use Happy\Clock\Models\PayClockDay;
use Happy\Clock\Service\ClockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ClockRedInit extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'clock:init-red';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Init Clock Red Number And Every Clocked User Reward Money (初始化已打卡用户拆分的数量和奖励金额)';

    public function __construct()
    {
        parent::__construct();

    }

    /**
     * STEP 5 定时任务瓜分红包金额，并将红包放入缓存中
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $this->handle();
    }

    public function handle()
    {
        $clockDayFormat = strtotime(Carbon::yesterday()->format('Y-m-d'));
        // todo start 测试活动日期
//        $clockDayFormat = strtotime(Carbon::now()->format('Y-m-d'));
        // todo end 测试活动日期
        $this->redInit($clockDayFormat);
        $message = sprintf("日期：%s, 打卡日: %s, 红包数据初始化完毕 ~", Carbon::yesterday()->format('Y-m-d'), $clockDayFormat);
        Log::channel(Config::get('clock.log_file'))->info($message);
    }

    private function redInit($clockDayFormat)
    {
        $clockService = new ClockService();
        $result = $clockService->initUserClockRed($clockDayFormat);
        if (empty($result['status'])) {
            Log::channel(Config::get('clock.log_file'))->info($result['message']);
            return false;
        }
        Log::channel(Config::get('clock.log_file'))->info($result['message']);
        $todayClockMoney = $result['data']['red_items'];
        if (empty($todayClockMoney)) {
            Log::channel(Config::get('clock.log_file'))->info($result['message']);
            return false;
        }
        $logInfo = sprintf("初始化日期:%s, 红包个数:%s, 红包总额: %s 红包分布金额:%s", $clockDayFormat, count($todayClockMoney),
            array_sum($todayClockMoney), json_encode($todayClockMoney));
        Log::channel(Config::get('clock.log_file'))->info($logInfo);
        $where = [
            'red_status' => PayClockDay::RED_RELEASE_OFF,
            'clock_day' => $clockDayFormat,
            'clock_status' => PayClockDay::CLOCK_STATE_IN
        ];
        $count = PayClockDay::query()->where($where)->count();
        // 分配设置红包
        $limit = 1000;
        $end = ceil($count / $limit);
        $all = [];
        for ($i = 0; $i < $end; $i++) {
            $offset = $i * $limit;
            $payClockDayEntities = PayClockDay::query()->where($where)->offset($offset)->limit($limit)->select(['uid'])->get();
            if (is_null($payClockDayEntities)) {
                continue;
            }
            $payClockDayItems = $payClockDayEntities->toArray();
            foreach ($payClockDayItems as $key => $content) {
                shuffle($todayClockMoney);
                $currentRed = array_pop($todayClockMoney);
                $currentRed = $currentRed > 98 ? 98 : $currentRed;
                $key = $clockService->getUserClockRewardKey($clockDayFormat, $content['uid']);
                $all[$content['uid']] = $currentRed;
                Cache::add($key, $currentRed, 7*24*3600);
            }
        }
        // 存入top榜数据
        $topLuckyMoney = max($all);
        $uid = array_search($topLuckyMoney, $all);
        //最高金额加上保底打卡金额
        $initMoney = Config::get('clock.participate_clock_money');
        $basicClockMoney = empty($initMoney) ? 1 : $initMoney;
        $top = ['uid' => $uid, 'money' => $topLuckyMoney + $basicClockMoney];
        $topKey = $clockService->topUserClockKey($clockDayFormat);
        Cache::add($topKey, json_encode($top), 7*24*3600);

        return true;
    }
}
