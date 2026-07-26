<?php

namespace Tests\Feature\PhaseSix;

use App\Enums\OperationalStatus;
use App\Enums\ReceivingBoxTokenAction;
use App\Filament\Resources\ReceivingBoxes\Pages\CreateReceivingBox;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\ReceivingBoxQrCodeService;
use App\Services\ReceivingBoxService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivingBoxAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_two_creates_one_permanent_box_with_a_secure_non_sequential_token_and_audit(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();

        $box = app(ReceivingBoxService::class)->create($actor, [
            'organizational_unit_id' => $unit->id,
            'box_location' => 'Records counter, cabinet 2',
            'status' => OperationalStatus::Active,
        ]);

        $this->assertSame(64, strlen($box->qr_token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $box->qr_token);
        $this->assertNotSame((string) $unit->id, $box->qr_token);
        $this->assertSame(
            '/receiving-boxes/'.$box->qr_token,
            parse_url(route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]), PHP_URL_PATH),
        );
        $this->assertDatabaseHas('receiving_box_token_audits', [
            'receiving_box_id' => $box->id,
            'action' => ReceivingBoxTokenAction::Generated->value,
            'previous_token_fingerprint' => null,
            'new_token_fingerprint' => hash('sha256', $box->qr_token),
            'performed_by' => $actor->id,
        ]);
    }

    public function test_level_two_can_create_a_receiving_box_through_the_admin_form(): void
    {
        DeploymentSetting::factory()->create();
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();

        Livewire::test(CreateReceivingBox::class)
            ->fillForm([
                'organizational_unit_id' => $unit->id,
                'box_location' => 'Records lobby, shelf A',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $box = ReceivingBox::query()->where('organizational_unit_id', $unit->id)->firstOrFail();
        $this->assertSame('Records lobby, shelf A', $box->box_location);
        $this->assertSame(64, strlen($box->qr_token));
        $this->assertDatabaseCount('receiving_box_token_audits', 1);
    }

    public function test_database_and_service_enforce_one_box_per_organizational_unit(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $service = app(ReceivingBoxService::class);
        $service->create($actor, ['organizational_unit_id' => $unit->id]);

        try {
            $service->create($actor, ['organizational_unit_id' => $unit->id]);
            $this->fail('Expected service uniqueness validation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('receiving_boxes', 1);
        }

        $this->expectException(QueryException::class);
        ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
    }

    public function test_tokens_are_unique_and_qr_service_renders_the_authenticated_lookup_url(): void
    {
        $first = ReceivingBox::factory()->create();
        $second = ReceivingBox::factory()->create();

        $this->assertNotSame($first->qr_token, $second->qr_token);

        $svg = app(ReceivingBoxQrCodeService::class)->svg($first);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('viewBox=', $svg);
    }

    public function test_level_two_can_regenerate_and_the_old_token_is_invalidated_with_fingerprint_only_audit(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $service = app(ReceivingBoxService::class);
        $box = $service->create($actor, ['organizational_unit_id' => $unit->id]);
        $oldToken = $box->qr_token;

        $regenerated = $service->regenerateToken($actor, $box, [
            'ip_address' => '127.0.0.1',
            'device_info' => 'Phase Six Test Browser',
        ]);

        $this->assertNotSame($oldToken, $regenerated->qr_token);
        $this->actingAs($actor)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $oldToken]))
            ->assertNotFound();
        $this->actingAs($actor)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $regenerated->qr_token]))
            ->assertOk();

        $audit = $regenerated->tokenAudits()->latest('id')->firstOrFail();
        $this->assertSame(ReceivingBoxTokenAction::Regenerated, $audit->action);
        $this->assertSame(hash('sha256', $oldToken), $audit->previous_token_fingerprint);
        $this->assertSame(hash('sha256', $regenerated->qr_token), $audit->new_token_fingerprint);
        $this->assertNotSame($oldToken, $audit->previous_token_fingerprint);
        $this->assertSame('127.0.0.1', $audit->ip_address);
    }

    public function test_level_one_cannot_administer_boxes_and_active_box_requires_active_unit(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $levelOne = User::factory()->levelOne($unit)->create();

        try {
            app(ReceivingBoxService::class)->create($levelOne, [
                'organizational_unit_id' => $unit->id,
            ]);
            $this->fail('Expected Level 1 creation to be rejected.');
        } catch (AuthorizationException) {
            $this->actingAs($levelOne)
                ->get('/admin/receiving-boxes')
                ->assertForbidden();
        }

        $inactiveUnit = OrganizationalUnit::factory()->inactive()->create();
        $this->expectException(ValidationException::class);
        app(ReceivingBoxService::class)->create(
            User::factory()->levelTwo()->create(),
            [
                'organizational_unit_id' => $inactiveUnit->id,
                'status' => OperationalStatus::Active,
            ],
        );
    }
}
