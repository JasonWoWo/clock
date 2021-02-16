<?php


namespace Happy\Clock\Commands;

use Carbon\Carbon;
use Happy\Clock\Models\PayClock;
use Happy\Clock\Models\PayClockDay;
use Happy\Clock\Service\ClockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ClockPushRed extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'clock:push-red';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push Participator Without Drawing Red (推送前天已打卡未领取红包的用户)';

    public function __construct()
    {
        parent::__construct();

    }

    /**
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
        $clockDayFormat = strtotime(date('Y-m-d', strtotime('-2 days')));
        // 修正失败人数
        $this->setFailureParticipator($clockDayFormat);
        $this->sendRedWithoutDrawing($clockDayFormat);
        $message = sprintf("日期：%s, 打卡日: %s, 红包数据初始化完毕 ~", Carbon::yesterday()->format('Y-m-d'), $clockDayFormat);
        Log::channel(Config::get('clock.log_file'))->info($message);
    }

    public function setFailureParticipator($clockDayFormat = 0)
    {
        if (empty($clockDayFormat)) {
            return false;
        }
        $clockEntity = PayClock::query()->where('clock_day', $clockDayFormat)->first();
        if (is_null($clockEntity)) {
            return false;
        }
        $clock = $clockEntity->toArray();
        $failureParticipator = $clock['user_num'] - $clock['success_user_num'];
        PayClock::query()->where('clock_day', $clockDayFormat)->update([
            'failure_user_num' => $failureParticipator,
            'update_time' => date('Y-m-d H:i:s')
        ]);
        return true;
    }

    public function sendRedWithoutDrawing($clockDayFormat = 0)
    {
        $where = [
            'red_status' => PayClockDay::RED_RELEASE_OFF,
            'clock_day' => $clockDayFormat,
            'clock_status' => PayClockDay::CLOCK_STATE_IN
        ];
        $unReleaseRedCount = PayClockDay::query()->where($where)->count();
        // 如果前天的用户数量都发完毕，将不进行发放
        if ($unReleaseRedCount) {
            Log::channel(Config::get('clock.log_file'))->info("前日未发红包已经执行完毕,暂无可发送红包用户");
            return false;
        }
        // 分批发放红包
        $limit = 1000;
        $end = ceil($unReleaseRedCount / $limit);
        $clockService = new ClockService();
        for ($i = 0; $i < $end; $i++) {
            $offset = $i * $limit;
            $payClockDayEntities = PayClockDay::query()->where($where)->offset($offset)
                ->limit($limit)->select('*')->get()->toArray();
            if (empty($payClockDayEntities)) {
                Log::channel(Config::get('clock.log_file'))->info("暂无可发送红包用户");
                continue;
            }
            $handler = function ($orderNo, $uid) {
                return [
                    'return_code' => 'SUCCESS',
                    'result_code' => "SUCCESS",
                    'order_no' => $orderNo,
                    'uid' => $uid
                ];
            };
            array_walk($payClockDayEntities, function ($item) use ($clockService, $handler) {
                $assignResult = $clockService->assignUserRedMoney($item, $handler);

                if (empty($assignResult['status'])) {
                    Log::channel(Config::get('clock.log_file'))->info("ClockPushRed Service Failed, uid: {$item['uid']}", $item);
                    return false;
                }
                if (isset($assignResult['data']['error'])) {
                    // 插入错误日志
                    $clockService->addClockErrorLog($assignResult['data']['error']);
                }
            });
        }
    }
}
