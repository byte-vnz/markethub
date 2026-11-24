<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketSection extends Model
{
    public const STALL_STATUSES = ['occupied', 'vacant', 'reserved', 'maintenance', 'inactive'];

    protected $guarded = ["id"];
    protected $casts = ['sort_order' => 'integer'];

    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    public function stalls()
    {
        return $this->hasMany(Stall::class);
    }

    public function scopeInDisplayOrder($query)
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    public function scopeWithStallCounts($query)
    {
        $inMarket = function ($stalls) {
            return $stalls->whereColumn('stalls.market_id', 'market_sections.market_id');
        };
        $counts = ['stalls' => $inMarket];
        foreach (self::STALL_STATUSES as $status) {
            $counts['stalls as '.$status.'_count'] = function ($stalls) use ($inMarket, $status) {
                return $inMarket($stalls)->where('stalls.status', $status);
            };
        }
        return $query->withCount($counts);
    }
}
