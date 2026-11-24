<?php
namespace Tests\Unit;

use App\Services\RentalCalculationService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RentalCalculationTest extends TestCase
{
    public function test_calculates_base_rent_and_excludes_cusa_from_advance_and_deposit()
    {
        $this->assertSame([
            "floor_area" => "10.00",
            "rental_rate_sqm" => "20.00",
            "daily_cusa" => "10.00",
            "billing_days" => 30,
            "daily_rental" => "200.00",
            "monthly_rental" => "6000.00",
            "monthly_cusa" => "300.00",
            "total_monthly_rental_cusa" => "6300.00",
            "advance_rental" => "12000.00",
            "security_deposit" => "6000.00",
            "total_initial_payment" => "18000.00",
        ], (new RentalCalculationService())->calculate("10", "20", "10", 30));
    }

    /** @dataProvider billingDays */
    public function test_uses_the_supplied_billing_days($days)
    {
        $result = (new RentalCalculationService())->calculate("1", "1", "1", $days);
        $expected = bcadd((string) $days, "0", 2);
        $this->assertSame($expected, $result["monthly_rental"]);
        $this->assertSame($expected, $result["monthly_cusa"]);
        $this->assertSame((int) $days, $result["billing_days"]);
    }

    public function billingDays()
    {
        return [[1], [28], [29], ["31"], [366]];
    }

    public function test_rounds_daily_product_half_up_before_multiplying_days()
    {
        $result = (new RentalCalculationService())->calculate("0.15", "0.10", "0", 31);
        $this->assertSame("0.02", $result["daily_rental"]);
        $this->assertSame("0.62", $result["monthly_rental"]);
        $this->assertSame("1.24", $result["advance_rental"]);
        $this->assertSame("1.86", $result["total_initial_payment"]);
        $this->assertSame("0.00", $result["monthly_cusa"]);
    }

    public function test_preserves_exact_cents_without_float_conversion()
    {
        $result = (new RentalCalculationService())->calculate("1.00", "999999999.99", "0.01", 1);
        $this->assertSame("999999999.99", $result["daily_rental"]);
        $this->assertSame("1000000000.00", $result["total_monthly_rental_cusa"]);
        $this->assertSame("2999999999.97", $result["total_initial_payment"]);
    }

    /** @dataProvider invalidInputs */
    public function test_rejects_invalid_inputs_and_amounts_that_exceed_storage_capacity($area, $rate, $cusa, $days)
    {
        $this->expectException(ValidationException::class);
        (new RentalCalculationService())->calculate($area, $rate, $cusa, $days);
    }

    public function invalidInputs()
    {
        return [
            ["0", "20", "0", 30],
            ["-1", "20", "0", 30],
            ["100000000", "20", "0", 30],
            ["1.001", "20", "0", 30],
            [1.0, "20", "0", 30],
            [null, "20", "0", 30],
            ["1", "0", "0", 30],
            ["1", "-20", "0", 30],
            ["1", "20.001", "0", 30],
            ["1", "1e3", "0", 30],
            ["1", "1,000", "0", 30],
            ["1", "20", "-1", 30],
            ["1", "20", "0.001", 30],
            ["1", "20", 0.1, 30],
            ["1", "20", "0", 0],
            ["1", "20", "0", 367],
            ["1", "20", "0", "30.5"],
            ["1", "20", "0", 30.0],
            ["1", "20", "0", true],
            ["1", "20", "0", null],
            ["1", "1000000000000", "0", 1],
            ["1", "999999999999.99", "0", 1],
            ["1", "1", "999999999999.99", 2],
        ];
    }
}
