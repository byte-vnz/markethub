<?php

namespace App\Console\Commands;

use App\Services\{Ledger, SectionService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Schema};
use Symfony\Component\Console\Output\OutputInterface;

class ReconcileSections extends Command
{
    protected $signature = 'sections:reconcile';
    protected $description = 'Read-only section, stall, layout and financial preservation report.';

    public function handle()
    {
        if (DB::getDriverName() === 'mysql' && DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION READ ONLY');
        }
        $report = DB::transaction(function () {
            $sectionColumns = Schema::getColumnListing('market_sections');
            $stallColumns = Schema::getColumnListing('stalls');
            $sections = DB::table('market_sections')->orderBy('market_id')->orderBy('id')->get(['id', 'market_id', 'code', 'name', 'status']);
            $duplicates = [];
            foreach ($sections->groupBy('market_id') as $marketId => $records) {
                $names = $records->groupBy(function ($section) {
                    return SectionService::normalizedName($section->name);
                });
                foreach ($names as $normalized => $group) {
                    if ($group->count() > 1) {
                        $duplicates[] = ['market_id' => $marketId, 'normalized_name' => (string) $normalized, 'section_ids' => $group->pluck('id')->all()];
                    }
                }
            }
            $hasSectionLink = in_array('market_section_id', $stallColumns, true);
            $orphans = [];
            $mismatches = [];
            if ($hasSectionLink) {
                $links = DB::table('stalls AS s')->leftJoin('market_sections AS ms', 'ms.id', '=', 's.market_section_id');
                $orphans = (clone $links)->whereNull('ms.id')->orderBy('s.id')->pluck('s.id')->all();
                $mismatches = $links->whereColumn('s.market_id', '!=', 'ms.market_id')->orderBy('s.id')->pluck('s.id')->all();
            }
            $legacyColumns = array_values(array_filter($stallColumns, function ($name) {
                return $name !== 'market_section_id' && stripos($name, 'section') !== false;
            }));
            $unchanged = [
                'section_rows_sha256' => $this->digest($sections),
                'stall_links_sha256' => $hasSectionLink
                    ? $this->digest(DB::table('stalls')->orderBy('id')->select('id', 'market_id', 'market_section_id', 'status')->cursor()) : null,
                'layout_positions_sha256' => $this->digest(DB::table('stall_layout_positions')->orderBy('stall_id')
                    ->select('market_layout_id', 'stall_id', 'row', 'column', 'width', 'height')->cursor()),
            ];
            foreach (['market_layouts', 'stall_assignments', 'rental_agreements', 'lease_contracts', 'rental_assessments', 'rental_bills', 'payments', 'payment_allocations'] as $table) {
                $unchanged[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
            }
            $posted = DB::table('payments AS p')->where('p.status', 'posted');
            $withoutReversals = function ($query) {
                $query->selectRaw('1')->from('payment_reversals AS r')->whereColumn('r.payment_id', 'p.id');
            };
            $unchanged['financial'] = [
                'posted_bill_amount' => Ledger::sum(Ledger::bills(), 'rental_bills.amount'),
                'posted_payment_gross_amount' => Ledger::sum($posted, 'p.amount'),
                'effective_posted_payment_amount' => Ledger::sum((clone $posted)->whereNotExists($withoutReversals), 'p.amount'),
                'effective_payment_allocation_amount' => Ledger::sum((clone $posted)->join('payment_allocations AS a', 'p.id', '=', 'a.payment_id')
                    ->whereNotExists($withoutReversals), 'a.amount'),
                'posted_outstanding_balance' => Ledger::sum(Ledger::bills(), Ledger::balanceSql()),
            ];
            return [
                'captured_at' => now()->toIso8601String(),
                'mode' => 'read-only; discrepancies require review',
                'schema' => ['market_sections' => $sectionColumns, 'stalls' => $stallColumns],
                'counts' => [
                    'sections' => $sections->count(),
                    'stalls' => DB::table('stalls')->count(),
                    'layout_positions' => DB::table('stall_layout_positions')->count(),
                    'orphaned_stall_sections' => $hasSectionLink ? count($orphans) : null,
                    'market_mismatched_stall_sections' => $hasSectionLink ? count($mismatches) : null,
                    'normalized_duplicate_section_groups' => count($duplicates),
                ],
                'section_status_counts' => $sections->countBy('status'),
                'stall_status_counts' => DB::table('stalls')->selectRaw('status, COUNT(*) AS count')->groupBy('status')->pluck('count', 'status'),
                'sections_by_market' => $sections->groupBy('market_id'),
                'normalized_duplicate_groups' => $duplicates,
                'orphaned_stall_ids' => $orphans,
                'market_mismatched_stall_ids' => $mismatches,
                'legacy_stall_section_columns' => $legacyColumns,
                'preservation_controls' => $unchanged,
            ];
        });
        $this->output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        return 0;
    }

    private function digest(iterable $rows): string
    {
        $hash = hash_init('sha256');
        foreach ($rows as $row) {
            hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
        }
        return hash_final($hash);
    }
}
