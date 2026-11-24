<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantHelper extends Model
{
    protected $guarded = ['id'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignment()
    {
        return $this->belongsTo(StallAssignment::class, 'stall_assignment_id');
    }
}
