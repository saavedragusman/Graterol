<?php

namespace App\Providers;

use App\Domain\Butchery\Models\ButcheryOrder;
use App\Domain\Butchery\Models\ButcheryTemplate;
use App\Domain\Butchery\Policies\ButcheryOrderPolicy;
use App\Domain\Butchery\Policies\ButcheryTemplatePolicy;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Policies\ProductPolicy;
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
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(ButcheryTemplate::class, ButcheryTemplatePolicy::class);
        Gate::policy(ButcheryOrder::class, ButcheryOrderPolicy::class);
    }
}
