<?php


namespace Happy\Clock\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class MigrationCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'clock:migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a migration following the Clock specifications.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $this->handle();
    }

    /**
     * Execute the console command for Laravel 5.5+.
     *
     * @return void
     */
    public function handle()
    {
        $this->laravel->view->addNamespace('Clock', substr(__DIR__, 0, -8).'views');

        $payClockTable = Config::get('clock.pay_clock_table');
        $payClockDayTable = Config::get('clock.pay_clock_day_table');
        $payClockOrderTable = Config::get('clock.pay_clock_order_table');
        $payClockSettingTable = Config::get('clock.pay_clock_setting_table');
        $payClockUserTable = Config::get('clock.pay_clock_user_table');
        $payCLockUserErrorTable = Config::get('clock.pay_clock_user_error_log_table');
        $payClockUserLogTable = Config::get('clock.pay_clock_user_log_table');

        $migrations = compact('payClockTable', 'payClockDayTable', 'payClockOrderTable', 'payClockSettingTable',
            'payClockUserTable', 'payCLockUserErrorTable', 'payClockUserLogTable');

        $this->line('');
        $this->info("Table: $payClockTable, $payClockDayTable, $payClockOrderTable, $payClockSettingTable, 
            $payClockUserTable, $payCLockUserErrorTable, $payClockUserLogTable ");

        $message = "A migration that creates '$payClockTable', '$payClockDayTable', '$payClockOrderTable',
         '$payClockSettingTable', '$payClockUserTable', '$payCLockUserErrorTable', '$payClockUserLogTable'".
            " tables will be created in database/migrations directory";

        $this->comment($message);
        $this->line('');

        if ($this->confirm("Proceed with the migration creation? [Yes|no]", "Yes")) {
            $this->line('');

            $this->info("Creating migration...");

            if ($this->createMigration($migrations)) {

                $this->info("Migration successfully created!");
            } else {
                $this->error(
                    "Couldn't create migration.\n Check the write permissions".
                    " within the database/migrations directory."
                );
            }
        }

        $this->line('');
    }

    protected function createMigration($migrations = [])
    {
        $migrationFile = base_path("/database/migrations")."/".date('Y_m_d_His')."_clock_setup_tables.php";

        $output = $this->laravel->view->make('Clock::generators.migration')->with($migrations)->render();

        if (!file_exists($migrationFile) && $fs = fopen($migrationFile, 'x')) {
            fwrite($fs, $output);
            fclose($fs);
            return true;
        }

        return false;
    }
}
