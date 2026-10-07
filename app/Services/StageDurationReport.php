<?php

namespace App\Services;

use App\Models\StageEvent;
use App\Models\StallCause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan durasi tahap (FR8).
 *
 * Durasi dihitung dari peristiwa tahap, bukan dari medan status, sehingga
 * laporan tetap benar meskipun perkara sudah berpindah tahap berkali-kali.
 * Tahap yang selesai dan tahap yang masih berjalan dilaporkan terpisah. Tahap
 * yang masih berjalan diukur sampai tanggal acuan, yang masuk sebagai parameter.
 *
 * Laporan bersifat agregat dan tidak memuat identitas deteni, sehingga dapat
 * dibaca peran yang tidak berhak membaca berkas perkara.
 */
class StageDurationReport
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function build(Carbon $asOf, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $events = StageEvent::query()
            ->where('opened_at', '<=', $to ?? $asOf)
            ->when($from, fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('closed_at')
                ->orWhere('closed_at', '>=', $from)))
            ->get();

        $causes = StallCause::query()
            ->where('recorded_at', '<=', $to ?? $asOf)
            ->when($from, fn ($q) => $q->where('recorded_at', '>=', $from))
            ->get()
            ->groupBy('stage_code');

        return collect(config('sipasti.stages'))->map(function (string $label, int $stage) use ($events, $causes, $asOf) {
            $stageEvents = $events->where('stage_code', $stage);

            $completed = $stageEvents
                ->whereNotNull('closed_at')
                ->map(fn (StageEvent $e) => $this->days($e->opened_at, $e->closed_at))
                ->sort()
                ->values();

            $ongoing = $stageEvents
                ->whereNull('closed_at')
                ->map(fn (StageEvent $e) => $this->days($e->opened_at, $asOf))
                ->sort()
                ->values();

            $stageCauses = $causes->get($stage, collect())->countBy('cause_code');

            return [
                'stage' => $stage,
                'label' => $label,
                'completed_count' => $completed->count(),
                'completed_mean' => $completed->isEmpty() ? null : round($completed->avg(), 1),
                'completed_median' => $completed->isEmpty() ? null : $completed->median(),
                'completed_max' => $completed->max(),
                'ongoing_count' => $ongoing->count(),
                'ongoing_max' => $ongoing->max(),
                'stall_causes' => collect(array_keys(config('sipasti.stall_causes')))
                    ->mapWithKeys(fn (string $code) => [$code => $stageCauses->get($code, 0)])
                    ->all(),
            ];
        })->values();
    }

    private function days(Carbon $from, Carbon $to): int
    {
        return (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay());
    }
}
