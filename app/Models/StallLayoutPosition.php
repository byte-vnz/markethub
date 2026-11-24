<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StallLayoutPosition extends Model
{
    protected $guarded = ["id"];
    public function stall()
    {
        return $this->belongsTo(Stall::class);
    }
}
