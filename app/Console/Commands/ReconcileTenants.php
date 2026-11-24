<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Schema};

class ReconcileTenants extends Command
{
    protected $signature = 'tenants:reconcile';
    protected $description = 'Read-only tenant/business relationship, classification and financial control counts; no personal information.';

    public function handle()
    {
        if (DB::getDriverName() === 'mysql' && DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION READ ONLY');
        }
        $report = DB::transaction(function () {
            $counts = [];
            foreach (['tenants','businesses','stalls','stall_assignments','rental_rates','rental_agreements','rental_bills','payments','lease_contracts','business_categories','business_category_rental_rates','rental_policies','rental_assessments'] as $table) {
                $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : null;
            }
            $relationships = [
                'linked_businesses'=>DB::table('businesses AS b')->join('tenants AS t','t.id','=','b.tenant_id')->count(),
                'orphaned_businesses'=>DB::table('businesses AS b')->leftJoin('tenants AS t','t.id','=','b.tenant_id')->whereNull('t.id')->count(),
                'business_assignment_owner_mismatches'=>DB::table('stall_assignments AS a')->join('businesses AS b','b.id','=','a.business_id')->whereColumn('a.tenant_id','<>','b.tenant_id')->count(),
                'assignments_without_business'=>DB::table('stall_assignments')->whereNull('business_id')->count(),
                'invalid_assignment_business_links'=>DB::table('stall_assignments AS a')->leftJoin('businesses AS b','b.id','=','a.business_id')->whereNotNull('a.business_id')->whereNull('b.id')->count(),
                'potential_duplicate_name_groups'=>DB::query()->fromSub(DB::table('tenants')->selectRaw('LOWER(TRIM(name)) AS normalized_name')->groupByRaw('LOWER(TRIM(name))')->havingRaw('COUNT(*) > 1'),'duplicates')->count(),
                'unclassified_stalls'=>Schema::hasColumn('stalls','location_type') ? DB::table('stalls')->whereNull('location_type')->count() : $counts['stalls'],
                'unmapped_businesses'=>Schema::hasColumn('businesses','business_category_id') ? DB::table('businesses')->whereNull('business_category_id')->count() : $counts['businesses'],
                'legacy_manual_agreements'=>DB::table('rental_agreements')->whereNotNull('rental_rate_id')->count(),
                'legacy_rate_amount_discrepancies'=>DB::table('rental_agreements AS a')->join('rental_rates AS r','r.id','=','a.rental_rate_id')->whereColumn('a.monthly_amount','<>','r.amount')->count(),
            ];
            if (Schema::hasColumn('businesses','business_category_id') && $counts['business_categories'] !== null) {
                $relationships['invalid_business_category_links'] = DB::table('businesses AS b')->leftJoin('business_categories AS c','c.id','=','b.business_category_id')->whereNotNull('b.business_category_id')->whereNull('c.id')->count();
            }
            if ($counts['rental_assessments'] !== null) {
                $relationships['assessment_agreement_amount_discrepancies'] = DB::table('rental_agreements AS a')->join('rental_assessments AS r','r.id','=','a.rental_assessment_id')->whereColumn('a.monthly_amount','<>','r.total_monthly_rental_cusa')->count();
            }
            $postedPayments = DB::table('payments AS p')->where('p.status','posted');
            $effectivePayments = (clone $postedPayments)->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('payment_reversals AS r')->whereColumn('r.payment_id','p.id');
            });
            $allocations = DB::table('payment_allocations AS a')->join('payments AS p','p.id','=','a.payment_id')->where('p.status','posted')->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('payment_reversals AS r')->whereColumn('r.payment_id','p.id');
            });
            return [
                'captured_at'=>now()->toIso8601String(),
                'mode'=>'read-only aggregate reconciliation; no backfill or matching performed',
                'counts'=>$counts,
                'relationships'=>$relationships,
                'financial_controls'=>[
                    'posted_bill_amount'=>bcadd((string)DB::table('rental_bills')->where('status','posted')->sum('amount'),'0',2),
                    'posted_payment_gross_amount'=>bcadd((string)$postedPayments->sum('p.amount'),'0',2),
                    'effective_posted_payment_amount'=>bcadd((string)$effectivePayments->sum('p.amount'),'0',2),
                    'effective_payment_allocation_amount'=>bcadd((string)$allocations->sum('a.amount'),'0',2),
                ],
                'notes'=>[
                    'Name matches are review candidates, never automatic merges.',
                    'Legacy rate discrepancies compare stored agreement amounts with their linked legacy rates, not new category rates.',
                    'Unmapped/unclassified legacy agreements cannot be compared with category calculations until explicitly reconciled.',
                    'Gross payment control totals include reversed posted records; effective totals exclude reversals.',
                ],
            ];
        });
        $this->line(json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        return 0;
    }
}
