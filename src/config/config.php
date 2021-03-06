<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clock Pay Model
    |--------------------------------------------------------------------------
    | n元打卡活动每日记录表
    */
    'pay_clock_table' => 'pay_clock',

    /*
    |--------------------------------------------------------------------------
    | Clock Pay Day Model
    |--------------------------------------------------------------------------
    | 用户n元打卡记录表
    */
    'pay_clock_day_table' => 'pay_clock_day',

    /*
    |--------------------------------------------------------------------------
    | Clock Pay Day Order Model
    |--------------------------------------------------------------------------
    | 用户n元打卡订单表
    */
    'pay_clock_order_table' => 'pay_clock_order',

    /*
    |--------------------------------------------------------------------------
    | Clock Pay Setting Model
    |--------------------------------------------------------------------------
    | n元打卡红包配置表
    */
    'pay_clock_setting_table' => 'pay_clock_setting',

    /*
    |--------------------------------------------------------------------------
    | Clock Pay User Model
    |--------------------------------------------------------------------------
    | 用户n元打卡活动用户表
    */
    'pay_clock_user_table' => 'pay_clock_user',

    /*
    |--------------------------------------------------------------------------
    | Clock Pay User Error Log Model
    |--------------------------------------------------------------------------
    | 用户n元打卡活动打款成功数据更新失败记录表
    */
    'pay_clock_user_error_log_table' => 'pay_clock_user_error_log',

    /*
    |--------------------------------------------------------------------------
    | Clock Pay User Log Model
    |--------------------------------------------------------------------------
    | n元打卡用户金额变动记录表
    */
    'pay_clock_user_log_table' => 'pay_clock_user_log',
    /*
    |--------------------------------------------------------------------------
    | Get User Alert Key
    |--------------------------------------------------------------------------
    | 获取用户的提示弹窗的缓存Key
    */
    'prefix_alert_key' => 'pay_clock_day_alert_key_',
    /*
    |--------------------------------------------------------------------------
    | Get Top Clock User Key
    |--------------------------------------------------------------------------
    | 获取打卡的用户榜单缓存Key
    */
    'prefix_top_key' => 'pay_clock_top_key_',
    /*
    |--------------------------------------------------------------------------
    | Get Yesterday Clock Money Key
    |--------------------------------------------------------------------------
    | 获取昨日打卡金额Key
    */
    'clock_yesterday_cash_key' => 'pay_yesterday_clock_money_',
    /*
    |--------------------------------------------------------------------------
    | Participate Clock Money
    |--------------------------------------------------------------------------
    | 参与打卡的基本金额
    */
    'participate_clock_money' => 1,
    /*
    |--------------------------------------------------------------------------
    | Order Prefix Info
    |--------------------------------------------------------------------------
    | 订单的前缀信息
    */
    'order_prefix' => 'TTC',
    /*
    |--------------------------------------------------------------------------
    | Order Notify Cache Prefix
    |--------------------------------------------------------------------------
    | 异步订单通知的订单缓存前缀
    */
    'order_notify_cache_prefix' => 'mini_order_',
    /*
    |--------------------------------------------------------------------------
    | Red Lock Cache Prefix
    |--------------------------------------------------------------------------
    | 红包发送锁
    */
    'red_lock_prefix_key' => 'user_red_lock_key_',
    /*
    |--------------------------------------------------------------------------
    | Red Service ratio
    |--------------------------------------------------------------------------
    | 红包预留服务费 = (报名打卡总金额 - 成功打卡总金额) * red_service_ratio
    */
    'red_service_ratio' => 0.05,
    /*
    |--------------------------------------------------------------------------
    | Red Proportion
    |--------------------------------------------------------------------------
    | 运营红包配置信息
    */
    'red_proportion' => [
        'num' => 2, // 配置发放2个红包
        'proportion' => 0.2, // 红包的占比为20%
    ],
    /*
    |--------------------------------------------------------------------------
    | Record Log File
    |--------------------------------------------------------------------------
    | 打卡服务日志文件
    */
    'log_file' => 'clock-server',
];
