<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketLayout extends Model
{
    protected $guarded = ["id"];
    public function positions()
    {
        return $this->hasMany(StallLayoutPosition::class);
    }
}
