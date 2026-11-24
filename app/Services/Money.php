<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class Money
{
    public static function normalize($value, $signed = false)
    {
        $pattern = $signed ? '/^-?\d{1,12}(\.\d{1,2})?$/' : '/^\d{1,12}(\.\d{1,2})?$/';
        if (!is_string($value) && !is_int($value)) {
            throw ValidationException::withMessages([
                "amount" => "Use a decimal amount with at most two decimal places.",
            ]);
        }
        if (!preg_match($pattern, (string) $value)) {
            throw ValidationException::withMessages(["amount" => "Invalid amount."]);
        }
        return bcadd((string) $value, "0", 2);
    }
    public static function positive($value)
    {
        $amount = self::normalize($value);
        if (bccomp($amount, "0", 2) <= 0) {
            throw ValidationException::withMessages(["amount" => "Amount must be positive."]);
        }
        return $amount;
    }
}
