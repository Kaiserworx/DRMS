<?php

namespace App\Providers;

use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Observers\SignificantActivityObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(fn (): Password => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols());

        foreach ([
            DeploymentSetting::class,
            OrganizationalUnit::class,
            User::class,
            DocumentType::class,
            DocumentOrigin::class,
            Document::class,
            ReceivingBox::class,
        ] as $model) {
            $model::observe(SignificantActivityObserver::class);
        }

        RateLimiter::for(
            'drms-receiving-box-lookups',
            fn (Request $request): Limit => Limit::perMinute(60)
                ->by('box-lookup:'.$request->ip()),
        );
        RateLimiter::for(
            'drms-receiving-box-claims',
            fn (Request $request): Limit => Limit::perMinute(10)
                ->by('box-claim:'.($request->user()?->getAuthIdentifier() ?? 'guest').':'.$request->ip()),
        );
        RateLimiter::for(
            'drms-report-exports',
            fn (Request $request): Limit => Limit::perMinute(30)
                ->by('report-export:'.($request->user()?->getAuthIdentifier() ?? 'guest').':'.$request->ip()),
        );

        $applicationUrl = rtrim((string) config('app.url'), '/');

        if (parse_url($applicationUrl, PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
            URL::useOrigin($applicationUrl);
        }
    }
}
