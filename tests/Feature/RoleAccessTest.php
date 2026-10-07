<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\HistoricalCaseSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\LegalStatusSeeder;
use Database\Seeders\SimulatedCaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pembatasan akses berdasarkan peran (NFR2).
 *
 * Prinsipnya perlu-tahu. Pengujian terpenting di sini adalah penolakan: auditor
 * dan admin tidak dapat membuka berkas perkara yang memuat identitas deteni.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([LegalStatusSeeder::class, IndicatorSeeder::class, SimulatedCaseSeeder::class, HistoricalCaseSeeder::class]);
    }

    #[Test]
    public function guests_are_sent_to_the_login_page(): void
    {
        $this->get('/board')->assertRedirect('/login');
        $this->get('/cases/1')->assertRedirect('/login');
        $this->get('/audit')->assertRedirect('/login');
    }

    /**
     * Matriks rute baca per peran. true berarti boleh, false berarti ditolak.
     */
    #[Test]
    #[DataProvider('readMatrix')]
    public function each_role_reads_only_what_it_needs(string $role, array $expected): void
    {
        $user = User::factory()->role($role)->create();

        foreach ($expected as $uri => $allowed) {
            $response = $this->actingAs($user)->get($uri);
            $allowed ? $response->assertOk() : $response->assertForbidden();
        }
    }

    public static function readMatrix(): array
    {
        return [
            'petugas' => ['petugas', [
                '/board' => true, '/cases/1' => true, '/cases/create' => true,
                '/reports/stage-durations' => false, '/audit' => false, '/indicators' => false,
            ]],
            'penyelia' => ['penyelia', [
                '/board' => true, '/cases/1' => true, '/cases/create' => true,
                '/reports/stage-durations' => true, '/audit' => false, '/indicators' => false,
            ]],
            'kepala' => ['kepala', [
                '/board' => true, '/cases/1' => true, '/cases/create' => false,
                '/reports/stage-durations' => true, '/audit' => false, '/indicators' => false,
            ]],
            'auditor' => ['auditor', [
                '/board' => false, '/cases/1' => false, '/cases/create' => false,
                '/reports/stage-durations' => true, '/audit' => true, '/indicators' => false,
            ]],
            'admin' => ['admin', [
                '/board' => false, '/cases/1' => false, '/cases/create' => false,
                '/reports/stage-durations' => false, '/audit' => true, '/indicators' => true,
            ]],
        ];
    }

    #[Test]
    public function a_clerk_cannot_close_an_alert_or_change_a_status(): void
    {
        $clerk = User::factory()->role('petugas')->create();

        $this->actingAs($clerk)->post('/cases/1/status', [
            'legal_status_code' => 'REFUGEE_RECOGNISED',
            'status_determination_ref' => 'X', 'status_determination_authority' => 'Y', 'status_determination_date' => '2026-10-01',
        ])->assertForbidden();

        $this->assertSame('DETENI', \App\Models\ImmigrationCase::find(1)->legal_status_code);
    }

    #[Test]
    public function a_refused_access_is_written_to_the_audit_trail(): void
    {
        $auditor = User::factory()->role('auditor')->create(['email' => 'auditor@uji.test']);

        $this->actingAs($auditor)->get('/cases/1')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'actor' => 'auditor:auditor@uji.test',
            'action' => 'access.denied',
            'entity_id' => 'cases.show',
        ]);
    }

    #[Test]
    public function an_inactive_account_loses_every_ability(): void
    {
        $user = User::factory()->role('penyelia')->create(['active' => false]);

        $this->actingAs($user)->get('/board')->assertForbidden();
    }

    #[Test]
    public function an_inactive_account_cannot_log_in(): void
    {
        User::factory()->role('penyelia')->create(['email' => 'nonaktif@uji.test', 'active' => false]);

        $this->post('/login', ['email' => 'nonaktif@uji.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'login.failed')->count());
    }

    #[Test]
    public function login_lands_each_role_on_its_own_home_page(): void
    {
        $this->seed(\Database\Seeders\UserSeeder::class);

        $expected = [
            'petugas' => '/board',
            'auditor' => '/reports/stage-durations',
            'admin' => '/indicators',
        ];

        foreach ($expected as $role => $home) {
            $this->post('/login', ['email' => "{$role}@sipasti.test", 'password' => 'password'])->assertRedirect('/');
            $this->get('/')->assertRedirect($home);
            $this->post('/logout');
        }
    }
}
