<?php echo '<?php' ?>

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ClockSetupTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        DB::beginTransaction();

        // Create table for storing pay_clock
        Schema::create('{{ $payClockTable }}', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('clock_day')->default('0')->nullable(false)->comment('打卡日期')->index('day');
        $table->integer('user_num')->default('0')->nullable(false)->comment('当天活动参与人数');
        $table->integer('success_user_num')->default('0')->nullable(false)->comment('成功人数');
        $table->integer('failure_user_num')->default('0')->nullable(false)->comment('失败人数');
        $table->integer('clock_money')->unsigned()->default('0')->nullable(false)->comment('奖池金额 单位分');
        $table->dateTime('create_time')->comment('创建时间')->nullable();
        $table->dateTime('update_time')->comment('更新时间')->nullable();
    });

    Schema::create('{{ $payClockDayTable }}', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('uid')->default('0')->nullable(false)->unsigned()->comment('用户UID')->index('uid');
        $table->string('nickname', 50)->collation('utf8mb4_general_ci')->default('')->comment('用户手机号');
        $table->string('mobile', 15)->collation('utf8_unicode_ci')->default('')->comment('用户手机号');
        $table->string('source_uid', 50)->collation('utf8_unicode_ci')->default('')->comment('平台用户ID');
        $table->tinyInteger('red_status')->default(1)->comment('红包是否发放 1否 2是');
        $table->integer('clock_day')->unsigned()->nullable(false)->default('0')->comment('用户参与打卡日期')->index('clock_day');
        $table->string('order_no', 50)->nullable(false)->default('')->collation('utf8_unicode_ci')->comment('支付订单号');
        $table->integer('order_money')->nullable(false)->default('0')->unsigned()->comment('订单支付金额 单位分');
        $table->integer('clock_money')->nullable(false)->default('0')->unsigned()->comment('打卡红包金额 单位分');
        $table->integer('discount_money')->nullable(false)->default('0')->unsigned()->comment('优惠金额 单位分');
        $table->tinyInteger('clock_status')->nullable(false)->default('0')->unsigned()->comment('打卡状态 1 已参与未打卡 2已打卡');
        $table->dateTime('clock_time')->comment('打卡时间')->nullable();
        $table->text('pay_result')->collation('utf8_unicode_ci')->nullable()->comment('红包发放回调返回值');
        $table->dateTime('red_time')->nullable()->comment('红包发放时间');
        $table->dateTime('create_time')->comment('创建时间')->nullable();
        $table->dateTime('update_time')->comment('更新时间')->nullable();
        $table->unique(['uid', 'clock_day'], 'uid_key');

    });

    Schema::create('{{ $payClockOrderTable }}', function (Blueprint $table) {
        $table->increments('id');
        $table->string('order_no', 50)->nullable(false)->default('')->comment('订单编号，不同于id')->index('order_no_index');
        $table->integer('clock_day')->nullable(false)->default('0')->comment('打卡活动时间');
        $table->integer('user_id')->nullable(false)->unsigned()->default('0')->comment('用户id');
        $table->string('transaction_id', 50)->collation('utf8_unicode_ci')->default('')->comment('渠道订单号');
        $table->string('buyer_id', 50)->collation('utf8_unicode_ci')->default('')->comment('平台用户ID');
        $table->tinyInteger('order_status')->nullable(false)->unsigned()->default('0')->comment('订单状态 1待支付 2支付成功');
        $table->integer('pay_amount')->nullable(false)->unsigned()->default('0')->comment('实际支付金额 单位分');
        $table->integer('discount_money')->nullable(false)->unsigned()->default('0')->comment('优惠金额 单位分');
        $table->text('pay_result')->collation('utf8_unicode_ci')->nullable()->comment('支付回调信息');
        $table->dateTime('pay_time')->nullable()->comment('支付时间');
        $table->dateTime('create_time')->comment('创建时间')->nullable();
        $table->dateTime('update_time')->comment('更新时间')->nullable();
    });

    // n元打卡活动用户表
    Schema::create('{{ $payClockUserTable }}', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('uid')->default('0')->nullable(false)->unsigned()->comment('用户UID')->index('uid')->unique();
        $table->string('nickname', 50)->collation('utf8mb4_general_ci')->default('')->comment('用户手机号');
        $table->string('mobile', 15)->collation('utf8_unicode_ci')->default('')->comment('用户手机号');
        $table->string('source_uid', 50)->collation('utf8_unicode_ci')->default('')->comment('平台用户ID');
        $table->tinyInteger('is_remind')->unsigned()->nullable(false)->default('0')->comment('是否开启打卡提醒 0关闭 1开启');
        $table->integer('spend_money')->unsigned()->nullable(false)->default('0')->comment('总支出金额 单位分');
        $table->integer('income_money')->unsigned()->nullable(false)->default('0')->comment('总收入金额 单位分');
        $table->integer('clock_max_money')->unsigned()->nullable(false)->default('0')->comment('活动获得最大奖金');
        $table->integer('clock_num')->unsigned()->nullable(false)->default('0')->comment('参与活动次数');
        $table->integer('clock_day_num')->unsigned()->nullable(false)->default('0')->comment('成功打卡天数');
        $table->integer('long_day_num')->unsigned()->nullable(false)->default('0')->comment('连续打卡天数');
        $table->dateTime('yesterday_time')->comment('上一次打卡时间')->nullable();
        $table->dateTime('create_time')->comment('创建时间')->nullable();
        $table->dateTime('update_time')->comment('更新时间')->nullable();
    });

    Schema::create('{{ $payClockUserLogTable }}', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('uid')->default('0')->nullable(false)->unsigned()->comment('用户UID')->index('uid');
        $table->integer('clock_day')->nullable(false)->default('0')->comment('参与打卡活动时间')->index('day');
        $table->tinyInteger('money_type')->unsigned()->nullable(false)->default('0')->comment('');
        $table->integer('change_money')->unsigned()->nullable(false)->default('0')->comment('变动金额 单位分');
        $table->string('log_content', 512)->collation('utf8_unicode_ci')->default('')->comment('log内容');
        $table->dateTime('create_time')->comment('创建时间')->nullable();
        $table->dateTime('update_time')->comment('更新时间')->nullable();

    });

    Schema::create('{{ $payClockSettingTable }}', function (Blueprint $table) {
        $table->increments('id');
        $table->dateTime('start_time')->nullable(false)->comment('开始时间');
        $table->dateTime('end_time')->nullable(false)->comment('开始时间');
        $table->integer('money_proportion')->unsigned()->nullable(false)->default('0')->comment('红包所占百分比');
        $table->integer('red_num')->unsigned()->nullable(false)->default('1')->comment('红包个数');
        $table->dateTime('create_time')->comment('创建时间')->nullable();
        $table->dateTime('update_time')->comment('更新时间')->nullable();
    });

    Schema::create('{{ $payCLockUserErrorTable }}', function (Blueprint $table) {
        $table->increments('id');
        $table->integer('clock_day_id')->unsigned()->nullable(false)->default('0')->comment('打卡列表主键ID');
        $table->integer('clock_day')->unsigned()->nullable(false)->default('0')->comment('打卡活动日期');
        $table->decimal('red_money', 10, 2)->default('0.00')->comment('打卡红包金额');
        $table->string('order_no', 50)->nullable(false)->default('')->comment('打款订单号');
        $table->integer('uid')->default('0')->nullable(false)->unsigned()->comment('用户UID');
        $table->text('error_msg')->collation('utf8_unicode_ci')->comment('失败原因');
        $table->tinyInteger('status')->default('1')->comment('修复状态  1未修复 2已修复');
        $table->text('pay_result')->collation('utf8_unicode_ci')->comment('支付返回结果');
        $table->dateTime('create_time')->comment('创建时间')->nullable();
        $table->dateTime('update_time')->comment('更新时间')->nullable();
    });

    DB::commit();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('{{ $payClockTable }}');
        Schema::dropIfExists('{{ $payClockDayTable }}');
        Schema::dropIfExists('{{ $payClockOrderTable }}');
        Schema::dropIfExists('{{ $payClockUserTable }}');
        Schema::dropIfExists('{{ $payClockUserLogTable }}');
        Schema::dropIfExists('{{ $payClockSettingTable }}');
        Schema::dropIfExists('{{ $payCLockUserErrorTable }}');
    }
}
