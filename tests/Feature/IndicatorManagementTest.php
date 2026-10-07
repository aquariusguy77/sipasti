<?php

namespace Tests\Feature;

use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Models\Indicator;
use App\Models\User;
use App\Services\IndicatorEngine;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\LegalStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ambang sebagai data (subbagian 4.3.1).
 *
 * Perubahan pedoman diterapkan dengan menyunting baris melalui antarmuka, dan
 * mesin indikator langsung mengikuti tanpa perubahan kode.
 */
class IndicatorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LegalStatusSeeder::class, IndicatorSeeder::class]);
    }

    #[Test]
    public function editing_a_threshold_row_changes_the_engine_and_is_audited(): void
    {
        $asOf = Carbon::parse('2026-10-07');
        $detainee = Detainee::create(['full_name' => 'Deteni Uji', 'sex' => 'L', 'nationality' => 'Negara Uji', 'travel_document_no' => 'TD']);
        $case = ImmigrationCase::create([
            'case_number' => 'UJI-1',
            'detainee_id' => $detainee->id,
            'legal_status_code' => 'DETENI',
            'detention_order_no' => 'SPR-UJI',
            'detention_order_date' => $asOf->copy()->subDays(8),
            'detention_room_type' => 'KANIM',
            'current_stage' => 2,
            'state' => 'open',
        ])->load('detainee', 'legalStatus', 'stageEvents', 'missionContacts');

        $engine = app(IndicatorEngine::class);
        $this->assertSame('peringatan', $engine->evaluate($case, Indicator::find('I1'), $asOf));

        $this->actingAs(User::factory()->role('admin')->create(['email' => 'admin@uji.test']))
            ->put('/indicators/I1', [
                'threshold_value' => '10',
                'normative_basis' => 'Revisi pedoman bentukan',
                'triggered_action' => 'Ingatkan unit asal mengajukan pemindahan ke Rudenim',
                'active' => '1',
            ])->assertRedirect('/indicators');

        $this->assertNull($engine->evaluate($case, Indicator::find('I1'), $asOf));

        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'admin:admin@uji.test',
            'action' => 'indicator.updated',
            'entity_id' => 'I1',
        ]);
    }

    #[Test]
    public function a_time_based_indicator_cannot_lose_its_threshold(): void
    {
        $this->actingAs(User::factory()->role('admin')->create())
            ->put('/indicators/I1', [
                'threshold_value' => '',
                'normative_basis' => 'x',
                'triggered_action' => 'y',
            ])->assertSessionHasErrors('threshold_value');

        $this->assertSame('7', Indicator::find('I1')->threshold_value);
    }

    #[Test]
    public function only_the_admin_edits_thresholds(): void
    {
        $this->actingAs(User::factory()->role('penyelia')->create())
            ->put('/indicators/I1', ['threshold_value' => '99', 'normative_basis' => 'x', 'triggered_action' => 'y'])
            ->assertForbidden();

        $this->assertSame('7', Indicator::find('I1')->threshold_value);
    }
}
