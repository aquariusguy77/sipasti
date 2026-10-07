<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\ImmigrationCase;
use App\Models\Indicator;
use App\Services\AuditLogger;
use App\Support\ReferenceDate;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Papan perkara (FR7).
 *
 * Papan menampilkan perkara terbuka per tahap beserta peringatan terbukanya.
 * Kartu perkara sengaja tidak memuat nama maupun kebangsaan deteni. Yang dipantau
 * adalah keadaan berkas perkara, bukan sifat orangnya, sehingga papan tidak
 * mengundang pemilahan berdasarkan asal negara.
 */
class BoardController extends Controller
{
    public function index(Request $request, AuditLogger $audit): View
    {
        $asOf = ReferenceDate::resolve();
        $tier = $request->query('tier');
        $indicator = $request->query('indicator');
        $search = trim((string) $request->query('q'));

        $cases = ImmigrationCase::with([
            'legalStatus',
            'alerts' => fn ($q) => $q->where('state', 'open'),
        ])
            ->where('state', 'open')
            ->when($search !== '', fn ($q) => $q->where('case_number', 'like', "%{$search}%"))
            ->when($tier || $indicator, fn ($q) => $q->whereHas('alerts', fn ($a) => $a
                ->where('state', 'open')
                ->when($tier, fn ($a) => $a->where('tier', $tier))
                ->when($indicator, fn ($a) => $a->where('indicator_code', $indicator))))
            ->orderBy('detention_order_date')
            ->get()
            ->each(function (ImmigrationCase $case) use ($asOf) {
                $case->board_days = $case->detentionDays($asOf);
                $case->board_tier = $case->alerts
                    ->sortByDesc(fn (Alert $a) => array_search($a->tier, Alert::TIERS, true))
                    ->first()?->tier;
            });

        $openAlerts = Alert::with('immigrationCase', 'indicator')
            ->where('state', 'open')
            ->whereHas('immigrationCase', fn ($q) => $q->where('state', 'open'))
            ->when($tier, fn ($q) => $q->where('tier', $tier))
            ->when($indicator, fn ($q) => $q->where('indicator_code', $indicator))
            ->get()
            ->sortBy([
                fn (Alert $a, Alert $b) => array_search($b->tier, Alert::TIERS, true) <=> array_search($a->tier, Alert::TIERS, true),
                fn (Alert $a, Alert $b) => $a->raised_at <=> $b->raised_at,
            ]);

        $tierCounts = Alert::where('state', 'open')
            ->whereHas('immigrationCase', fn ($q) => $q->where('state', 'open'))
            ->selectRaw('tier, count(*) as total')
            ->groupBy('tier')
            ->pluck('total', 'tier');

        $audit->read($request->user()->auditActor(), 'Board');

        return view('board', [
            'asOf' => $asOf,
            'stages' => config('sipasti.stages'),
            'columns' => $cases->groupBy('current_stage'),
            'caseCount' => $cases->count(),
            'openAlerts' => $openAlerts,
            'tierCounts' => $tierCounts,
            'indicators' => Indicator::all()->sortBy(fn (Indicator $i) => (int) substr($i->code, 1))->values(),
            'filters' => ['tier' => $tier, 'indicator' => $indicator, 'q' => $search],
        ]);
    }
}
