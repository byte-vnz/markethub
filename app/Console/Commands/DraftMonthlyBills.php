<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\{User, StallAssignment};
use App\Services\BillingService;
class DraftMonthlyBills extends Command
{
    protected $signature = "billing:draft {period : YYYY-MM} {--due= : YYYY-MM-DD} {--actor= : Authorized user ID}";
    protected $description = "Generate draft monthly bills only; never post collections or bills";
    public function handle(BillingService $service)
    {
        $actor = User::find($this->option("actor"));
        if (!$actor || !$actor->active || !$actor->can("billing.generate")) {
            $this->error("Supply an active authorized --actor.");
            return 1;
        }
        if (!$this->option("due")) {
            $this->error("Supply an explicit approved --due date.");
            return 1;
        }
        $failed = 0;
        StallAssignment::where("status", "active")
            ->orderBy("id")
            ->chunkById(100, function ($items) use ($service, $actor, &$failed) {
                foreach ($items as $a) {
                    try {
                        $b = $service->generate(
                            $actor,
                            $a,
                            $this->argument("period"),
                            $this->option("due"),
                        );
                        $this->line($b->reference . " " . $b->status);
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        $failed++;
                        $this->warn(
                            "Assignment " .
                                $a->id .
                                ": " .
                                implode(" ", \Illuminate\Support\Arr::flatten($e->errors())),
                        );
                    }
                }
            });
        $this->info("Draft generation finished. No bills were automatically posted.");
        return $failed ? 1 : 0;
    }
}
