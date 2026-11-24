<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Stall extends Model
{
    protected $guarded = ["id"];
    protected $casts = ['area' => 'decimal:2'];
    public function market()
    {
        return $this->belongsTo(Market::class);
    }
    public function section()
    {
        return $this->belongsTo(MarketSection::class, "market_section_id");
    }
    public function scopeInActiveSection($query)
    {
        return $query->whereHas('section', function ($section) {
            $section->where('market_sections.status', 'active')->whereColumn('market_sections.market_id', 'stalls.market_id');
        });
    }
    public function category()
    {
        return $this->belongsTo(StallCategory::class, "stall_category_id");
    }
    public function assignments()
    {
        return $this->hasMany(StallAssignment::class);
    }
    public function currentAssignment()
    {
        return $this->hasOne(StallAssignment::class, "active_stall_id");
    }
    public function position()
    {
        return $this->hasOne(StallLayoutPosition::class);
    }
}
