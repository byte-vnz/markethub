<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class ContractNumberService
{
    public function next()
    {
        $period = today()->format("Y-m");
        DB::table("contract_sequences")->insertOrIgnore(["period" => $period, "last_number" => 0]);
        $row = DB::table("contract_sequences")->where("period", $period)->lockForUpdate()->first();
        $number = $row->last_number + 1;
        DB::table("contract_sequences")
            ->where("period", $period)
            ->update(["last_number" => $number]);
        $prefix = config("lease.control_prefix");
        if (!is_string($prefix) || !preg_match('/^[A-Z0-9-]{2,20}$/', $prefix)) {
            throw new \RuntimeException("Configure a valid contract numbering prefix.");
        }
        $format = config("lease.number_format");
        if (
            !is_string($format) ||
            strpos($format, "{period}") === false ||
            strpos($format, "{sequence}") === false
        ) {
            throw new \RuntimeException(
                "Number format must retain period and sequence uniqueness.",
            );
        }
        $value = strtr($format, [
            "{prefix}" => $prefix,
            "{period}" => $period,
            "{sequence}" => str_pad((string) $number, 6, "0", STR_PAD_LEFT),
        ]);
        if (!preg_match('/^[A-Za-z0-9-]{1,100}$/', $value)) {
            throw new \RuntimeException("Unsupported contract number format.");
        }
        return $value;
    }
}
