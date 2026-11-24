<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ContractTemplate extends Model
{
    protected $guarded = ["id"];
    protected $casts = ["approved" => "boolean"];
    protected static function booted()
    {
        static::updating(function ($model) {
            foreach (["path", "source_checksum", "checksum", "version"] as $key) {
                if ($model->isDirty($key)) {
                    throw new \LogicException(
                        "Register a new template version instead of replacing its source.",
                    );
                }
            }
        });
        static::deleting(function () {
            throw new \LogicException("Templates are retained for history.");
        });
    }
}
