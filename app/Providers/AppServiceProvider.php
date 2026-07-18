<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\PresentationModality;
use App\Models\ProcedureCategory;
use App\Models\ProcedureDynamicField;
use App\Models\ProcedurePrerequisite;
use App\Models\ProcedureRequirement;
use App\Models\ProcedureType;
use App\Models\ProcedureVariant;
use App\Models\RequestDocument;
use App\Models\User;
use App\Observers\CatalogAuditObserver;
use App\Observers\UserAuditObserver;
use App\Policies\ConfigurableCatalogPolicy;
use App\Policies\RequestDocumentPolicy;
use Illuminate\Pagination\Paginator;
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
        Paginator::useBootstrapFive();
        Area::observe(CatalogAuditObserver::class);
        ProcedureType::observe(CatalogAuditObserver::class);
        ProcedureRequirement::observe(CatalogAuditObserver::class);
        ProcedureCategory::observe(CatalogAuditObserver::class);
        PresentationModality::observe(CatalogAuditObserver::class);
        ProcedureVariant::observe(CatalogAuditObserver::class);
        ProcedureDynamicField::observe(CatalogAuditObserver::class);
        ProcedurePrerequisite::observe(CatalogAuditObserver::class);
        Gate::policy(ProcedureCategory::class, ConfigurableCatalogPolicy::class);
        Gate::policy(PresentationModality::class, ConfigurableCatalogPolicy::class);
        Gate::policy(ProcedureVariant::class, ConfigurableCatalogPolicy::class);
        Gate::policy(ProcedureDynamicField::class, ConfigurableCatalogPolicy::class);
        Gate::policy(ProcedurePrerequisite::class, ConfigurableCatalogPolicy::class);
        Gate::policy(RequestDocument::class, RequestDocumentPolicy::class);
        User::observe(UserAuditObserver::class);
    }
}
