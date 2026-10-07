<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Services\AuditLogger;
use App\Services\IndicatorEngine;
use App\Support\ReferenceDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ambang sebagai data (subbagian 4.3.1).
 *
 * Perubahan pedoman diterapkan dengan menyunting baris tabel indicators melalui
 * halaman ini, bukan dengan membangun ulang sistem. Setiap suntingan dicatat
 * pada log audit beserta nilai lama dan nilai baru.
 */
class IndicatorController extends Controller
{
    private const EDITABLE = ['threshold_value', 'normative_basis', 'triggered_action', 'active'];

    public function index(): View
    {
        return view('indicators.index', [
            'indicators' => Indicator::all()->sortBy(fn (Indicator $i) => (int) substr($i->code, 1)),
        ]);
    }

    public function update(Request $request, Indicator $indicator, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            // Satu bilangan, atau beberapa bilangan dipisah koma untuk mode tonggak.
            'threshold_value' => ['nullable', 'regex:/^\d+(,\d+)*$/'],
            'normative_basis' => ['required', 'string', 'max:2000'],
            'triggered_action' => ['required', 'string', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');

        if ($indicator->mode !== 'condition' && blank($data['threshold_value'])) {
            return back()->withErrors(['threshold_value' => 'Indikator berbasis waktu menuntut nilai ambang.']);
        }

        $before = $indicator->only(self::EDITABLE);
        $indicator->update($data);
        $changes = collect($indicator->getChanges())->keys()
            ->map(fn ($k) => "{$k}: ".json_encode($before[$k]).' -> '.json_encode($indicator->{$k}))
            ->implode('; ');

        if ($changes !== '') {
            $audit->write($request->user()->auditActor(), 'indicator.updated', 'Indicator', $indicator->code, $changes);
        }

        return redirect()->route('indicators.index')->with('status', "Indikator {$indicator->code} diperbarui.");
    }

    public function run(Request $request, IndicatorEngine $engine): RedirectResponse
    {
        $asOf = ReferenceDate::resolve();
        $raised = $engine->run($asOf, $request->user()->auditActor());

        return back()->with('status', "Evaluasi indikator per {$asOf->toDateString()}: {$raised->count()} peringatan baru atau naik tingkat.");
    }
}
