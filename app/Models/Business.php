<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Business extends Model
{
    protected $guarded = ["id"];
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    public function businessCategory()
    {
        return $this->belongsTo(BusinessCategory::class);
    }
}
