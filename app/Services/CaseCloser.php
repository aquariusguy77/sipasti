<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\ImmigrationCase;
use App\Models\StageEvent;
use App\Services\Exceptions\ReviewRequiredException;
use App\Services\Exceptions\StatusGateException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penutupan perkara.
 *
 * Perkara tidak dapat ditutup selama masih memiliki peringatan terbuka. Setiap
 * peringatan harus lebih dahulu ditinjau dan ditutup dengan alasan (FR10),
 * sehingga penutupan perkara tidak menjadi jalan pintas untuk menghilangkan
 * peringatan yang belum ditangani.
 *
 * Penutupan dengan alasan deportasi melewati gerbang status.
 */
class CaseCloser
{
    public function __construct(
        private readonly StatusGate $gate,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @throws ReviewRequiredException
     * @throws StatusGateException
     */
    public function close(ImmigrationCase $case, Carbon $date, string $reason, string $actor = 'system'): void
    {
        if (! array_key_exists($reason, config('sipasti.closure_reasons'))) {
            throw new InvalidArgumentException("Alasan penutupan {$reason} tidak dikenal.");
        }

        if ($case->state !== 'open') {
            throw new InvalidArgumentException('Perkara sudah ditutup.');
        }

        if ($reason === 'deported') {
            $this->gate->assertDeportationAllowed($case, $actor);
        }

        $openAlerts = Alert::where('immigration_case_id', $case->id)->where('state', 'open')->count();

        if ($openAlerts > 0) {
            $this->audit->write($actor, 'case.close_refused', 'ImmigrationCase', $case->id, "peringatan_terbuka={$openAlerts}");

            throw new ReviewRequiredException(
                "Perkara masih memiliki {$openAlerts} peringatan terbuka yang harus ditinjau lebih dahulu."
            );
        }

        DB::transaction(function () use ($case, $date, $reason, $actor) {
            StageEvent::where('immigration_case_id', $case->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => $date]);

            $case->update(['state' => 'closed', 'closed_at' => $date, 'closure_reason' => $reason]);

            $this->audit->write($actor, 'case.closed', 'ImmigrationCase', $case->id, "alasan={$reason}");
        });
    }
}
