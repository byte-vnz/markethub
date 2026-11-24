<?php

namespace App\Support;

use App\Models\{RentalAssessment, StallAssignment};
use App\Services\RentalCalculationService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AssignmentRental
{
    public static function initial(StallAssignment $assignment): ?RentalAssessment
    {
        $assessment = optional($assignment->agreement)->assessment;
        return $assessment && $assessment->purpose === 'initial' && (int) $assessment->stall_assignment_id === (int) $assignment->id
            ? $assessment : null;
    }

    public static function quote(StallAssignment $assignment, string $period): array
    {
        if (!self::initial($assignment) || $period < substr($assignment->start_date, 0, 7) ||
            ($assignment->end_date && $period > substr($assignment->end_date, 0, 7))) {
            return [null, null];
        }
        try {
            return [app(RentalCalculationService::class)->forAssignment($assignment, $period), null];
        } catch (ValidationException $error) {
            return [null, $error->validator->errors()->first()];
        }
    }

    public static function earliestRequestMonth(StallAssignment $assignment): ?string
    {
        $initial = self::initial($assignment);
        if (!$initial || $assignment->status !== 'active' || $assignment->tenant->status !== 'active' ||
            !$assignment->business || $assignment->business->status !== 'active' ||
            (int) $assignment->business_id !== (int) $initial->business_id ||
            (int) $assignment->business->tenant_id !== (int) $assignment->tenant_id) {
            return null;
        }
        $start = Carbon::parse(max($assignment->start_date, today()->toDateString()));
        if ($start->day !== 1) {
            $start->addMonthNoOverflow()->startOfMonth();
        }
        return $assignment->end_date && $start->copy()->endOfMonth()->toDateString() > $assignment->end_date
            ? null : $start->toDateString();
    }
}
