<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Perkara, yaitu satuan pelacakan sekaligus satuan prediksi.
 *
 * Seluruh indikator membaca dari kelas ini dan turunannya. Tidak ada indikator
 * yang membaca dari Detainee.
 */
class ImmigrationCase extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'detention_order_date' => 'date',
        'deportation_decision_date' => 'date',
        'deportation_decision_valid_until' => 'date',
        'guarantee_revoked_at' => 'date',
        'status_determination_date' => 'date',
        'closed_at' => 'date',
        'funding_exhausted' => 'boolean',
    ];

    public function detainee(): BelongsTo
    {
        return $this->belongsTo(Detainee::class);
    }

    public function legalStatus(): BelongsTo
    {
        return $this->belongsTo(LegalStatus::class, 'legal_status_code', 'code');
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(StageEvent::class);
    }

    public function stallCauses(): HasMany
    {
        return $this->hasMany(StallCause::class);
    }

    public function missionContacts(): HasMany
    {
        return $this->hasMany(MissionContact::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Durasi detensi kumulatif dalam hari kalender (FR2, NFR1).
     */
    public function detentionDays(Carbon $asOf): int
    {
        return $this->detention_order_date->diffInDays($asOf);
    }

    /**
     * Tanggal perubahan tahap terakhir, dipakai oleh I9.
     */
    public function lastStageChange(): ?Carbon
    {
        $dates = $this->stageEvents
            ->flatMap(fn (StageEvent $e) => [$e->opened_at, $e->closed_at])
            ->filter()
            ->sort();

        return $dates->last();
    }

    /**
     * Kontak perwakilan negara terakhir yang belum memperoleh respons (I4).
     */
    public function openMissionContact(): ?MissionContact
    {
        return $this->missionContacts
            ->whereNull('response_date')
            ->sortBy('letter_date')
            ->last();
    }

    public function hasTravelDocument(): bool
    {
        return filled($this->detainee?->travel_document_no);
    }
}
