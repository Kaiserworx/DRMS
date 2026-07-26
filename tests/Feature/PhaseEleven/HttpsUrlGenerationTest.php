<?php

namespace Tests\Feature\PhaseEleven;

use App\Models\DeploymentSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HttpsUrlGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_application_url_generates_public_https_filament_assets_behind_a_proxy(): void
    {
        DeploymentSetting::factory()->create();

        config()->set('app.url', 'https://demo.example.test');
        URL::forceScheme(null);
        URL::useOrigin(null);

        (new AppServiceProvider($this->app))->boot();

        $this->get('http://internal-proxy/admin/login')
            ->assertOk()
            ->assertSee(
                'href="https://demo.example.test/css/filament/filament/app.css',
                false,
            )
            ->assertSee(
                'src="https://demo.example.test/js/filament/filament/app.js',
                false,
            );
    }
}
