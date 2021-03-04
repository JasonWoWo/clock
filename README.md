
### 1.参与活动流程图

### 2.包安装
#### 2.1 项目composer更新

2.1.1 execute composer 

```bash
composer require "olaf/clock": "dev-master"
```
#### 2.2 Provider配置文件
     2.2.1 Open your `config/app.php` and add the following to the `providers` array:
 
```bash
Happy\Clock\ClockServiceProvider::class
```
2.1.2 Run the command below to publish the package config file `config/clock.php`

```bash
php artisan vendor:publish --provider="Happy\Clock\ClockServiceProvider"
```
#### 2.3 Clock配置更新
2.3.1 检测项目Migrate状态

```bash
➜ php artisan migrate:status
Migration table not found.
```
2.3.2 若未初始话，需要先初始化Migrate服务，否则，跳过该步骤

```bash
➜ php artisan migrate:install
Migration table created successfully.
```
2.3.3 再次检测Migrate状态

```bash
➜ php artisan migrate:status
+------+------------------------------------------------+-------+
| Ran? | Migration                                      | Batch |
+------+------------------------------------------------+-------+
| No   | 2014_10_12_000000_create_users_table           |       |
| No   | 2014_10_12_100000_create_password_resets_table |       |
| No   | 2019_08_19_000000_create_failed_jobs_table     |       |
+------+------------------------------------------------+-------+
```
2.3.4 生成Clock的数据库迁移信息
```bash
➜ php artisan clock:migration
/Users/wangxionghao/Server/package/clock/src/views
Table: pay_clock, pay_clock_day, pay_clock_order, pay_clock_setting,
            pay_clock_user, pay_clock_user_error_log, pay_clock_user_log
A migration that creates 'pay_clock', 'pay_clock_day', 'pay_clock_order',
         'pay_clock_setting', 'pay_clock_user', 'pay_clock_user_error_log', 'pay_clock_user_log' tables will be created in database/migrations directory

 Proceed with the migration creation? [Yes|no] (yes/no) [yes]:
 > yes


Creating migration...
Migration successfully created!
```
该命令执行后，生成 `<timestamp>_clock_setup_tables.php` 文件
2.3.5 数据库迁移
```bash
➜  php artisan migrate
Migrating: 2021_02_07_074236_clock_setup_tables
Migrated:  2021_02_07_074236_clock_setup_tables (274.25ms)

OR

➜  php artisan migrate --path=./database/migrations/<timestamp>_clock_setup_tables.php
```
2.3.6 日志文件配置 `config/logging.php`中在 `channels` 数组中添加如下内容
```bash
'clock-server' => [
            'driver' => 'daily',
            'path' => storage_path('logs/clock/info.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
 ],
```
### 3.接口服务
#### 3.1 获取参与打卡首页信息
**请求参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| uid | 平台用户ID | int |  |
| extra | 额外参数 | array |  |
| extra.mobile | 用户的手机号 | string |  |
| extra.nickname | 用户的昵称 | string |  |
| extra.openId | 用户在渠道的ID | string |  |



**响应参数模版：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| status | 业务状态码 | int | 0:表示业务失败，1:表示成功 |
| message | 业务响应内容 | string |  |
| data | 业务数据块 | array |  |

**响应参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| status | 业务状态码 | int | 0:表示业务失败，1:表示成功 |
| message | 业务响应内容 | string |  |
| data | 业务数据块 | array |  |
| data.summary | 数据概览 | array |  |
| data.summary.is_remind | 打卡提醒 | boolean | 0:表示关闭，1:表示开启 |
| data.summary.clock_status | 参与状态 | int | 0:未参加, 1:已参加, 2:已打卡 |
| data.summary.user_num | 参与人数 | int |  |
| data.summary.clock_user_num | 打卡成功人数 | int |  |
| data.summary.clock_money | 奖池奖金 | int | 按天参与报名的总金额 |
| data.summary.clock_time | 打卡倒计时 | int | 距离05:00的倒计时时间戳 |
| data.summary.red_time | 开奖倒计时 | int | 距离08:05的倒计时时间戳 |
| data.summary.zero_time | 零点开关 | int |  |
| data.summary.is_start | 活动是否开启 | boolean |  |
| data.summary.is_alert | 是否需要弹窗 | boolean |  |
| data.summary.yest_money | 昨日奖金池 | int |  |

#### 3.2 参与打卡活动支付
活动支付的流程为clock服务提供生成订单号，订单号及相关的业务参数初始化后，在向渠道发起支付请求
**请求地址：**
```bash
use Happy\Clock\CashClock;
use Happy\Clock\Service\ClockService;

public function prepay(Request $request, ClockService $service)
{
    $extra = [
        'nickname' => 'JasonABCDE',
        'mobile' => '18986299292',
        'openId' => 'oq_pO5XHsq87Z3LLBc7p0',
    ];
    $uid = 6;

    $cashClockSer = new CashClock($service);

    $result = $cashClockSer->generateClockOrder($uid, $extra);

    return Response::json($result);
}
```
**请求参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| uid | 平台用户ID | int |  |
| extra | 额外参数 | array |  |
| extra.mobile | 用户的手机号 | string |  |
| extra.nickname | 用户的昵称 | string |  |
| extra.openId | 用户在渠道的ID | string |  |

**响应参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| status | 业务状态码 | int | 0:表示业务失败，1:表示成功 |
| message | 业务响应内容 | string |  |
| data | 业务数据块 | array |  |
| data.order_id | 订单ID | int |  |
| data.order_no | 外部订单号 | string |  |

#### 3.3 异步回调通知
接受渠道的回调通知，按业务需求处理打卡订单逻辑及用户参与信息
**请求地址：**
```bash
use Happy\Clock\CashClock;
use Happy\Clock\Service\ClockService;

public function notify(Request $request, ClockService $service)
    {
        $cashClockSer = new CashClock($service);

        $orderNo = "TTC202102155324302";
        $extra = [
            'amount' => 100 / 100,
            'buyerId' => 'oq_pO5XHsq87Z3LLBc7p0',
            'notify_time' => date('Y-m-d H:i:s'),
            'transactionId' => "Wx2394446280110126",
            'content' => "<xml>
                          <return_code><![CDATA[SUCCESS]]></return_code>
                          <result_code><![CDATA[SUCCESS]]></result_code>
                          <sign><![CDATA[C380BEC2BFD727A4B6845133519F3AD6]]></sign>
                          <mch_id>10010404</mch_id>
                          <contract_code>100001256</contract_code>
                          <openid><![CDATA[oAubD08j8Y4GU6YNyq_aGWC8h2OA]]></openid>
                          <plan_id><![CDATA[1000888]]></plan_id>
                          <change_type><![CDATA[DELETE]]></change_type>
                          <operate_time><![CDATA[2015-07-01 10:00:00]]></operate_time>
                          <contract_id><![CDATA[Wx15463511252015071056489715]]></contract_id>
													</xml>",
            "nickname" => "JasonABCDE",
            "mobile" => "18986299292"
        ];


        $result = $cashClockSer->notifyClockOrder($orderNo, $extra);

        return Response::json($result);
    }
```
**请求参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| order_no | 外部订单号 | string |  |
| extra | 额外回调信息 | array |  |
| extra.amount | 回调通知订单金额 | int |  |
| extra.buyerId | 用户在渠道的ID信息 | string |  |
| extra.notify_time | 回调通知时间 | string |  |
| extra.content | 回调的内容块 | string |  |
| extra.nickname | 用户的昵称信息 | string |  |
| extra.mobile | 用户的手机信息 | string |  |
| extra.transactionId | 渠道订单号信息 | string |  |

**响应参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| status | 业务状态码 | int | 0:表示业务失败，1:表示成功 |
| message | 业务响应内容 | string |  |
| data | 业务数据块 | array |  |

#### 3.4 检测用户的打卡状态
该接口服务用于用户在打卡前的检测，防止已打卡的用户再次发起打卡操作；
**请求地址：**
```bash
ClockCenter/checkUserClock
```
**请求参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| uid | 平台用户ID | int |  |

**响应参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| status | 业务状态码 | int | 0:表示业务失败，1:表示成功 |
| message | 业务响应内容 | string |  |
| data | 业务数据块 | array |  |
| data.is_check_in | 是否已打卡 | boolean | 1:表示已打卡；0:表示未打卡 |

#### 3.5 用户参与打卡
**请求地址：**
```bash
use Happy\Clock\ClockCenter;
use Happy\Clock\Service\ClockService;

public function clock(Request $request, ClockService $service)
{
    $clockCenterSer = new ClockCenter($service);

    $uid = 4;

    $result = $clockCenterSer->clock($uid);

    return Response::json($result);
}
```
**请求参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| uid | 平台用户ID | int |  |

**响应参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| status | 业务状态码 | int | 0:表示业务失败，1:表示成功 |
| message | 业务响应内容 | string |  |
| data | 业务数据块 | array |  |
| data.money | 参与活动报名日的总奖金池 | int |  |

#### 3.6 系统定时任务红包瓜分
打卡活动在早上8点结束，系统开始定时任务，将总奖池的红包进行拆分业务，红包总额 = 参与活动报名的总奖金池 - 已成功打卡的总额；在把瓜分的红包放入缓存中；
**请求地址：**
```bash
Commands/ClockRedInit/fire

php artisan clock:init-red
```
#### 3.7 用户领取红包
定时任务将参与活动未打卡的金额转为奖金，瓜分到每个用户奖金，用户获得的金额为参与打卡费用+红包；
**请求地址：**
```bash
use Happy\Clock\CashClock;
use Happy\Clock\Service\ClockService;

public function openRed(Request $request, ClockService $service)
{
    $cashClockSer = new CashClock($service);

    $uid = 4;
	  // $handler闭包用于处理 各渠道(微信/支付宝)的转账/打款到用户的逻辑逻辑
    $handler = function ($orderNo, $uid) {
        return [
            'return_code' => 'SUCCESS',
            'result_code' => "SUCCESS",
            'order_no' => $orderNo,
            'uid' => $uid
        ];
    };
    $result = $cashClockSer->userOpenRed($uid, $handler);

    return Response::json($result);
}
```
**请求参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| uid | 平台用户ID | int |  |

**响应参数：**

| 字段 | 字段名称 | 类型 | 备注 |
| --- | --- | --- | --- |
| status | 业务状态码 | int | 0:表示业务失败，1:表示成功 |
| message | 业务响应内容 | string |  |
| data | 业务数据块 | array |  |
| data.assign | 分配的红包信息 | array |  |
| data.assign.money | 分配的红包金额 | int |  |



### 4.系统定时任务
#### 4.1 瓜分红包定时任务
每天早上8:05分执行，用于将未打卡的参与用户的活动金额瓜分；
```bash
php artisan clock:init-red

php artisan clock:push-red

php artisan clock:push-red
```


