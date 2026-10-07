<?php

namespace App\Services;

use App\Models\ImmigrationCase;
use App\Models\MissionContact;
use App\Models\StageEvent;
use App\Models\StallCause;
use App\Services\Exceptions\StatusGateException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pencatatan pergerakan perkara antar tahap (subbagian 4.3.1).
 *
 * Tahap dicatat sebagai peristiwa. Perpindahan tahap menutup peristiwa tahap
 * yang sedang berjalan dan membuka peristiwa baru, sehingga durasi tiap tahap
 * tetap dapat dihitung oleh laporan durasi tahap (FR8).
 *
 * Tahap pada jalur deportasi dijaga gerbang status. Penjagaan ini berada di
 * lapis layanan, sehingga tidak dapat dilewati melalui layar.
 */
class StageTracker
{
    public function __construct(
        private readonly StatusGate $gate,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @throws StatusGateException
     */
    public function advance(
        ImmigrationCase $case,
        int $toStage,
        Carbon $date,
        ?string $responsibleUnit = null,
        ?string $outputDocument = null,
        string $actor = 'system',
    ): StageEvent {
        if (! array_key_exists($toStage, config('sipasti.stages'))) {
            throw new InvalidArgumentException("Tahap {$toStage} tidak dikenal.");
        }

        if ($case->state !== 'open') {
            throw new InvalidArgumentException('Perkara yang sudah ditutup tidak dapat berpindah tahap.');
        }

        if ($toStage === (int) $case->current_stage) {
            throw new InvalidArgumentException('Perkara sudah berada pada tahap tersebut.');
        }

        if (in_array($toStage, config('sipasti.deportation_stages'), true)) {
            $this->gate->assertDeportationAllowed($case, $actor);
        }

        return DB::transaction(function () use ($case, $toStage, $date, $responsibleUnit, $outputDocument, $actor) {
            $from = (int) $case->current_stage;

            StageEvent::where('immigration_case_id', $case->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => $date]);

            $event = StageEvent::create([
                'immigration_case_id' => $case->id,
                'stage_code' => $toStage,
                'opened_at' => $date,
                'responsible_unit' => $responsibleUnit,
                'output_document' => $outputDocument,
            ]);

            $case->update(['current_stage' => $toStage]);

            $this->audit->write($actor, 'stage.changed', 'ImmigrationCase', $case->id, "dari={$from}; ke={$toStage}");

            return $event;
        });
    }

    public function recordStallCause(
        ImmigrationCase $case,
        string $causeCode,
        Carbon $date,
        ?string $note = null,
        string $actor = 'system',
    ): StallCause {
        if (! in_array($causeCode, StallCause::CAUSES, true)) {
            throw new InvalidArgumentException("Sebab tertahan {$causeCode} tidak dikenal.");
        }

        $cause = StallCause::create([
            'immigration_case_id' => $case->id,
            'stage_code' => $case->current_stage,
            'cause_code' => $causeCode,
            'recorded_at' => $date,
            'note' => $note,
        ]);

        $this->audit->write($actor, 'stall_cause.recorded', 'ImmigrationCase', $case->id, "sebab={$causeCode}");

        return $cause;
    }

    /**
     * Surat kepada perwakilan negara merupakan langkah jalur pemulangan,
     * sehingga dijaga gerbang status, sama seperti indikator I3 dan I4.
     *
     * @throws StatusGateException
     */
    public function recordMissionLetter(
        ImmigrationCase $case,
        string $mission,
        string $letterNo,
        Carbon $letterDate,
        string $actor = 'system',
    ): MissionContact {
        $this->gate->assertDeportationAllowed($case, $actor);

        $contact = MissionContact::create([
            'immigration_case_id' => $case->id,
            'mission' => $mission,
            'letter_no' => $letterNo,
            'letter_date' => $letterDate,
        ]);

        $this->audit->write($actor, 'mission_letter.recorded', 'ImmigrationCase', $case->id, "surat={$letterNo}");

        return $contact;
    }

    public function recordMissionResponse(
        MissionContact $contact,
        Carbon $responseDate,
        string $responseType,
        string $actor = 'system',
    ): MissionContact {
        $contact->update(['response_date' => $responseDate, 'response_type' => $responseType]);

        $this->audit->write(
            $actor,
            'mission_response.recorded',
            'ImmigrationCase',
            $contact->immigration_case_id,
            "surat={$contact->letter_no}; respons={$responseType}",
        );

        return $contact;
    }
}
