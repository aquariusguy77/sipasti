<?php

namespace App\Services;

use App\Models\Detainee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aturan retensi (NFR5).
 *
 * Identitas deteni dianonimkan setelah seluruh perkaranya ditutup selama jangka
 * retensi. Yang dihapus hanya medan identitas. Data perkara dipertahankan agar
 * laporan durasi tahap tetap dapat dihitung, dan log audit tidak pernah disentuh
 * karena bersifat hanya-tambah.
 *
 * Tanggal acuan masuk sebagai parameter, sama seperti mesin indikator, sehingga
 * batas retensi dapat diuji tanpa mengubah jam sistem.
 */
class RetentionPolicy
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Deteni yang identitasnya sudah melewati jangka retensi.
     *
     * @return Collection<int, Detainee>
     */
    public function due(Carbon $asOf, ?int $years = null): Collection
    {
        $years ??= config('sipasti.retention_years');
        $cutoff = $asOf->copy()->subYears($years);

        return Detainee::query()
            ->whereNull('anonymised_at')
            ->whereHas('cases')
            // Satu perkara yang masih terbuka cukup untuk menahan anonimisasi.
            ->whereDoesntHave('cases', fn ($q) => $q->where(fn ($q) => $q
                ->where('state', 'open')
                ->orWhereNull('closed_at')
                ->orWhere('closed_at', '>', $cutoff)))
            ->get();
    }

    /**
     * @return Collection<int, Detainee> deteni yang dianonimkan
     */
    public function apply(Carbon $asOf, string $actor = 'system', ?int $years = null): Collection
    {
        $due = $this->due($asOf, $years);

        foreach ($due as $detainee) {
            DB::transaction(function () use ($detainee, $asOf, $actor) {
                $detainee->update([
                    'full_name' => 'ANONIM-'.$detainee->id,
                    'birth_place' => null,
                    'birth_date' => null,
                    'travel_document_no' => null,
                    'travel_document_place' => null,
                    'travel_document_date' => null,
                    'anonymised_at' => $asOf,
                ]);

                $this->audit->write($actor, 'retention.anonymised', 'Detainee', $detainee->id);
            });
        }

        return $due;
    }
}
