<?php
namespace App\Console;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        if (
            config("billing.scheduled_drafts") &&
            config("billing.actor") &&
            config("billing.due_day") >= 1 &&
            config("billing.due_day") <= 28
        ) {
            $due = today()
                ->startOfMonth()
                ->addDays(config("billing.due_day") - 1)
                ->toDateString();
            $schedule
                ->command("billing:draft", [
                    today()->format("Y-m"),
                    "--due" => $due,
                    "--actor" => config("billing.actor"),
                ])
                ->monthlyOn(1, "01:00")
                ->timezone("Asia/Manila")
                ->withoutOverlapping();
        }
    }
    protected function commands()
    {
        $this->load(__DIR__ . "/Commands");
        require base_path("routes/console.php");
    }
}
