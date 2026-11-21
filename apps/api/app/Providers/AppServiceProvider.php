<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Policies\GuardianChildPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
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
        Gate::define('view-child', [GuardianChildPolicy::class, 'viewChild']);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
    }
}
