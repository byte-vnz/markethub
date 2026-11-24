<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Market extends Model
{
    protected $guarded = ["id"];
    public function sections()
    {
        return $this->hasMany(MarketSection::class);
    }
    public function stalls()
    {
        return $this->hasMany(Stall::class);
    }
}
