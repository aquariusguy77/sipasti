<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\StageDurationReport;
use App\Support\ReferenceDate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * Laporan durasi tahap (FR8).
 */
class ReportController extends Controller
{
    public function stageDurations(Request $request, StageDurationReport $report, AuditLogger $audit): View|StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'format' => ['nullable', 'in:html,csv'],
        ]);

        $asOf = ReferenceDate::resolve();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : null;
        $to = isset($data['to']) ? Carbon::parse($data['to']) : null;
        $rows = $report->build($asOf, $from, $to);

        $audit->read($request->user()->auditActor(), 'StageDurationReport');

        if (($data['format'] ?? 'html') === 'csv') {
            return $this->csv($rows, $asOf);
        }

        return view('reports.stage-durations', [
            'rows' => $rows,
            'asOf' => $asOf,
            'filters' => ['from' => $data['from'] ?? null, 'to' => $data['to'] ?? null],
            'causes' => config('sipasti.stall_causes'),
        ]);
    }

    private function csv($rows, Carbon $asOf): StreamedResponse
    {
        $causes = config('sipasti.stall_causes');

        return response()->streamDownload(function () use ($rows, $causes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(
                ['tahap', 'nama_tahap', 'selesai_jumlah', 'selesai_rerata_hari', 'selesai_median_hari', 'selesai_maks_hari', 'berjalan_jumlah', 'berjalan_maks_hari'],
                array_map(fn ($c) => "sebab_{$c}", array_keys($causes)),
            ));

            foreach ($rows as $row) {
                fputcsv($out, array_merge([
                    $row['stage'], $row['label'], $row['completed_count'], $row['completed_mean'],
                    $row['completed_median'], $row['completed_max'], $row['ongoing_count'], $row['ongoing_max'],
                ], array_values($row['stall_causes'])));
            }

            fclose($out);
        }, "durasi-tahap-{$asOf->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }
}
