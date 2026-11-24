<?php
namespace App\Services;
use App\Models\AuditLog;
class Audit
{
    public static function record(
        $actor,
        $action,
        $entity = null,
        array $old = [],
        array $new = [],
        $reason = null
    ) {
        $safeFields = array_flip(self::safeFields());
        if ($entity instanceof \App\Models\MarketSection) {
            $safeFields += array_flip(['market_id', 'code', 'name', 'color']);
        } elseif ($entity instanceof \App\Models\Stall) {
            $safeFields += array_flip(['market_id', 'market_section_id']);
        } elseif ($entity instanceof \App\Models\RentalOverride || $entity instanceof \App\Models\RentalAssessment) {
            $safeFields += array_flip(['stall_assignment_id','initial_assessment_id','source_override_id','revision',
                'override_type','override_rate_per_sqm','override_daily_amount','automatic_daily_rental','automatic_rate_per_sqm',
                'rental_difference','daily_rental','proposed_daily_rental','rental_override_id','computation_mode','rental_policy_id','requested_by',
                'submitted_at','rejected_at','rejected_by','revoked_at','revoked_by','revocation_effective_from']);
        } elseif ($entity instanceof \App\Models\RentalPolicy) {
            $safeFields += array_flip(['allow_out_of_range_overrides','allow_zero_rent_overrides','override_authority_reference']);
        }
        return AuditLog::create([
            "actor_id" => $actor ? $actor->id : null,
            "action" => $action,
            "entity_type" => $entity ? class_basename($entity) : null,
            "entity_id" => $entity ? $entity->id : null,
            "old_values" => array_intersect_key(
                $old,
                $safeFields,
            ),
            "new_values" =>
                [
                    "changed_fields" => array_values(
                        array_diff(array_keys($new), [
                            "password",
                            "remember_token",
                            "request_hash",
                            "idempotency_key",
                        ]),
                    ),
                ] +
                array_intersect_key(
                    $new,
                    $safeFields,
                ),
            "ip_address" => app()->runningInConsole() ? null : request()->ip(),
            "reason" => $reason,
        ]);
    }
    private static function safeFields()
    {
        return ['status','amount','start_date','end_date','monthly_amount','business_category_id','location_type','area','floor_area','minimum_rate','maximum_rate','effective_from','effective_until','daily_cusa','billing_day_rule','fixed_billing_days','max_concurrent_assignments','advance_deposit_authorized','is_active','sort_order','reference_document','approved_by','approved_at','rental_assessment_id'];
    }
}
