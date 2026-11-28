<?php

namespace App\Providers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Policies\GuardianChildPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use Illuminate\Database\Query\Builder;
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

        Builder::macro('whereIlike', function (string $column, string $pattern, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $wrapped = $this->getGrammar()->wrap($column);
            if ($this->getConnection()->getDriverName() === 'pgsql') {
                return $this->whereRaw("{$wrapped} ILIKE ?", [$pattern], $boolean);
            }

            return $this->whereRaw("LOWER({$wrapped}) LIKE LOWER(?)", [$pattern], $boolean);
        });

        Builder::macro('orWhereIlike', function (string $column, string $pattern): Builder {
            /** @var Builder $this */
            return $this->whereIlike($column, $pattern, 'or');
        });

        Builder::macro('whereNotIlike', function (string $column, string $pattern, string $boolean = 'and'): Builder {
            /** @var Builder $this */
            $wrapped = $this->getGrammar()->wrap($column);
            if ($this->getConnection()->getDriverName() === 'pgsql') {
                return $this->whereRaw("{$wrapped} NOT ILIKE ?", [$pattern], $boolean);
            }

            return $this->whereRaw("LOWER({$wrapped}) NOT LIKE LOWER(?)", [$pattern], $boolean);
        });
    }
}
