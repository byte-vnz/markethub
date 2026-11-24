<?php

namespace Tests\Feature;

use App\Models\{Market, RentalRate, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyRateIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_page_of_legacy_rates_resolves_market_names_in_one_query()
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('Super Administrator');
        $markets = [
            Market::create(['name' => 'Fictional Market North', 'code' => 'N']),
            Market::create(['name' => 'Fictional Market South', 'code' => 'S']),
        ];

        for ($index = 1; $index <= 20; $index++) {
            RentalRate::create([
                'market_id' => $markets[$index % 2]->id,
                'classification' => sprintf('Fictional rate %02d', $index),
                'amount' => '2500.00',
                'effective_from' => '2020-01-01',
                'approval_reference' => 'Fictional test fixture',
            ]);
        }

        DB::enableQueryLog();
        try {
            $this->actingAs($user)->get(route('records.index', 'rates'))->assertOk()
                ->assertSeeInOrder(['Fictional Market North', 'Fictional rate 20', 'Fictional Market South', 'Fictional rate 19', 'Fictional rate 01']);
            $marketQueries = array_filter(DB::getQueryLog(), static function ($query) {
                return preg_match('/\bfrom ["`]?markets\b/i', $query['query']);
            });
            $this->assertCount(1, $marketQueries, 'Market names should be fetched once for the current rate page.');
        } finally {
            DB::disableQueryLog();
        }
    }
}
