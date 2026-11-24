<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model
{
    protected $guarded = ["id"];
    protected $casts = ["old_values" => "array", "new_values" => "array"];
    public function scopeVisibleTo($query, ?User $actor)
    {
        if (!$actor || !$actor->can(['rentals.override.view', 'rentals.override.history'])) {
            $query->where('action', '!=', 'assessment.override_applied')->where(function ($query) {
                $query->whereNull('entity_type')->orWhere('entity_type', '!=', 'RentalOverride');
            });
        }
        return $query;
    }
    public function actor()
    {
        return $this->belongsTo(User::class, "actor_id");
    }
    protected static function booted()
    {
        static::updating(function () {
            throw new \LogicException("Audit records are immutable.");
        });
        static::deleting(function () {
            throw new \LogicException("Audit records are immutable.");
        });
    }
}
