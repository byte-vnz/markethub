<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RentalRate extends Model
{
    protected $guarded = ["id"];
    public function market()
    {
        return $this->belongsTo(Market::class);
    }
}
