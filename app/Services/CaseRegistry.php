<?php

namespace App\Services;

use App\Models\Detainee;
use App\Models\ImmigrationCase;
use App\Models\StageEvent;
use App\Services\Exceptions\StatusGateException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registrasi dan pembaruan data perkara.
 *
 * Medan identitas dibatasi pada apa yang sudah diwajibkan Kartu Deteni dan
 * berita acara pendetensian (NFR4). Log audit mencatat nama medan yang berubah,
 * bukan nilainya, agar log audit tidak menjadi salinan kedua data pribadi.
 */
class CaseRegistry
{
    public const DETAINEE_FIELDS = [
        'full_name', 'sex', 'birth_place', 'birth_date', 'nationality',
        'travel_document_no', 'travel_document_place', 'travel_document_date',
        'sending_agency', 'provision_violated', 'biometric_captured',
    ];

    public const CASE_FIELDS = [
        'detention_room_type', 'funding_source', 'funding_exhausted', 'guarantee_revoked_at',
        'deportation_decision_no', 'deportation_decision_date', 'deportation_decision_valid_until',
    ];

    private const DEPORTATION_FIELDS = [
        'deportation_decision_no', 'deportation_decision_date', 'deportation_decision_valid_until',
    ];

    public function __construct(
        private readonly StatusGate $gate,
        private readonly AuditLogger $audit,
    ) {
    }

    public function register(array $detainee, array $case, int $initialStage, Carbon $stageOpenedAt, string $actor): ImmigrationCase
    {
        return DB::transaction(function () use ($detainee, $case, $initialStage, $stageOpenedAt, $actor) {
            $person = Detainee::create($detainee);

            $record = ImmigrationCase::create($case + [
                'detainee_id' => $person->id,
                'current_stage' => $initialStage,
                'state' => 'open',
            ]);

            StageEvent::create([
                'immigration_case_id' => $record->id,
                'stage_code' => $initialStage,
                'opened_at' => $stageOpenedAt,
            ]);

            $this->audit->write($actor, 'case.registered', 'ImmigrationCase', $record->id, "tahap={$initialStage}");

            return $record;
        });
    }

    /**
     * @throws StatusGateException
     */
    public function update(ImmigrationCase $case, array $caseData, array $detaineeData, string $actor): void
    {
        $case->fill($caseData);
        $case->detainee->fill($detaineeData);

        $caseChanges = array_keys($case->getDirty());
        $detaineeChanges = array_keys($case->detainee->getDirty());

        if (array_intersect($caseChanges, self::DEPORTATION_FIELDS)
            && collect(self::DEPORTATION_FIELDS)->contains(fn ($f) => filled($case->{$f}))) {
            $this->gate->assertDeportationAllowed($case, $actor);
        }

        if (! $caseChanges && ! $detaineeChanges) {
            return;
        }

        DB::transaction(function () use ($case, $caseChanges, $detaineeChanges, $actor) {
            $case->save();
            $case->detainee->save();

            $fields = implode(',', array_merge($caseChanges, array_map(fn ($f) => "deteni.{$f}", $detaineeChanges)));
            $this->audit->write($actor, 'case.updated', 'ImmigrationCase', $case->id, "medan={$fields}");
        });
    }
}
