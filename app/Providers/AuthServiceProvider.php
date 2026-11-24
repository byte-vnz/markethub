<?php
namespace App\Providers;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        foreach (
            [
                "Market",
                "MarketSection",
                "MarketLayout",
                "Stall",
                "StallCategory",
                "StallLayoutPosition",
                "Tenant",
                "Business",
                "StallAssignment",
                "RentalAgreement",
                "RentalRate",
                "RentalBill",
                "Payment",
            ]
            as $model
        ) {
            Gate::policy("App\\Models\\" . $model, \App\Policies\RecordPolicy::class);
        }
    }
}
