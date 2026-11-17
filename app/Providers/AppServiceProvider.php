<?php

namespace App\Providers;

use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Policies\PurchasePolicy;
use App\Domain\Purchasing\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
    }
}
