<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessCategorySeeder extends Seeder
{
    private const SEED_SOURCE = 'Source: supplied Business Category Rental Rate Masterlist.';

    public function run()
    {
        $categories = [
            ['AMBULANT', 'Ambulant Vendors', '24.89', '34.84'],
            ['PREPARED-FOOD', 'Prepared Food', '33.74', '42.18', 'Bakery, carinderia, food kiosks, and other cooked/prepared food ready for consumption.'],
            ['CHICKEN', 'Chicken', '52.37', '64.27'],
            ['COCONUT', 'Coconut', '18.27', '22.84'],
            ['FISH', 'Fish', '52.37', '64.27'],
            ['FRUITS', 'Fruits', '30.97', '30.97'],
            ['GRAINS-STAPLES', 'Grains and Staples', '18.27', '22.84'],
            ['GROCERY', 'Grocery Items', '24.89', '34.84', 'Salt, charcoal, dry goods, delicatessen, egg, herbal, house and kitchen items, lumpia wrapper, plastic bag, and other grocery items.'],
            ['ICE', 'Ice', '23.57', '33.63'],
            ['JEWELRY', 'Jewelry', '21.10', '28.13'],
            ['MEAT', 'Meat', '52.37', '64.27'],
            ['NIGHT-MARKET', 'Night Market', '24.89', '34.84'],
            ['OTHER-SERVICES', 'Other Services', '21.10', '28.13', 'Locksmith, optical, printing, sewing/tailor shop, watch repair, and other services.'],
            ['PARLOR-BARBERSHOP', 'Parlor/Barbershop and other similar services', '21.10', '28.13'],
            ['RTW', 'RTW', '21.10', '28.13', 'Accessories, footwear, RTW.'],
            ['SPECIALTY', 'Specialty', '52.37', '64.27', 'Cosmetics, drugstore, party needs and other specialty merchandise.'],
            ['CELLPHONE', 'Cellphone, Cellphone Accessories, Gadgets', '34.84', '40.00'],
            ['IT-EQUIPMENT', 'Other IT related equipments/items', '34.84', '40.00'],
            ['SCHOOL-SUPPLIES', 'School Supplies', '24.89', '34.84'],
            ['SWEETS', 'Sweets', '21.10', '28.13'],
            ['VEGETABLES', 'Vegetables', '21.04', '26.88'],
        ];

        DB::transaction(function () use ($categories) {
            foreach ($categories as $index => $row) {
                [$code, $name, $min, $max] = $row;
                $category = BusinessCategory::firstOrCreate(['code' => $code], [
                    'name' => $name,
                    'description' => $row[4] ?? null,
                    'sort_order' => $index + 1,
                ]);
                $category = BusinessCategory::lockForUpdate()->findOrFail($category->id);
                $rates = $category->rentalRates()->lockForUpdate()->get();
                if ($rates->isEmpty()) {
                    $category->rentalRates()->create([
                        'minimum_rate' => $min,
                        'maximum_rate' => $max,
                        'status' => 'draft',
                        'remarks' => self::SEED_SOURCE,
                    ]);
                } elseif ($code === 'IT-EQUIPMENT') {
                    $incompleteSeeds = $rates->filter(function ($rate) {
                        return $rate->status === 'draft' && !$rate->created_by && !$rate->approved_at && !$rate->approved_by
                            && !$rate->effective_from && !$rate->effective_until && !$rate->reference_document
                            && in_array($rate->remarks, [null, self::SEED_SOURCE], true)
                            && ($rate->minimum_rate === null || $rate->maximum_rate === null);
                    });
                    if ($incompleteSeeds->count() === 1) {
                        $rate = $incompleteSeeds->first();
                        if (Schema::hasTable('rental_assessments') && DB::table('rental_assessments')
                            ->where('business_category_rental_rate_id', $rate->id)->exists()) {
                            continue;
                        }
                        $rate->update([
                            'minimum_rate' => $rate->minimum_rate ?? $min,
                            'maximum_rate' => $rate->maximum_rate ?? $max,
                        ]);
                    }
                }
            }
        }, 3);
    }
}
