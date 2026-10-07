<?php

namespace App\Services;

use App\Models\ImmigrationCase;
use App\Models\LegalStatus;
use App\Services\Exceptions\StatusGateException;

/**
 * Gerbang status hukum (FR9, subbagian 4.3.4).
 *
 * Gerbang berada di lapis aplikasi, bukan di antarmuka, sehingga tidak dapat
 * dilewati melalui layar. Pembatasan menjadi sifat sistem, bukan soal disiplin
 * petugas.
 *
 * Gerbang ini mencegah sistem menjadi sarana pemulangan yang melanggar hukum.
 * Ia tidak mencegah keputusan yang diambil di luar sistem.
 */
class StatusGate
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * Memastikan alur deportasi boleh dijalankan atas suatu perkara.
     *
     * @throws StatusGateException
     */
    public function assertDeportationAllowed(ImmigrationCase $case, string $actor = 'system'): void
    {
        $status = LegalStatus::findOrFail($case->legal_status_code);

        if ($status->allows_deportation) {
            return;
        }

        $this->audit->write(
            $actor,
            'deportation.refused',
            'ImmigrationCase',
            $case->id,
            "status={$status->code}",
        );

        throw new StatusGateException(
            "Alur deportasi ditolak untuk status {$status->code}."
        );
    }

    public function deportationAllowed(ImmigrationCase $case): bool
    {
        return (bool) LegalStatus::find($case->legal_status_code)?->allows_deportation;
    }

    /**
     * Mengubah penanda status hukum.
     *
     * Perubahan tanpa rujukan penetapan ditolak. Otoritas yang menentukan status
     * perlindungan berada di luar Rumah Detensi Imigrasi, sehingga petugas tidak
     * dapat mengubah status dengan pernyataan sendiri.
     *
     * @throws StatusGateException
     */
    public function changeStatus(
        ImmigrationCase $case,
        string $newStatusCode,
        ?string $determinationRef,
        ?string $determinationAuthority,
        ?string $determinationDate,
        string $actor = 'system',
    ): void {
        if (blank($determinationRef) || blank($determinationAuthority) || blank($determinationDate)) {
            $this->audit->write(
                $actor,
                'status.change_refused',
                'ImmigrationCase',
                $case->id,
                "target={$newStatusCode}; alasan=rujukan penetapan tidak lengkap",
            );

            throw new StatusGateException(
                'Perubahan penanda status menuntut rujukan penetapan yang lengkap.'
            );
        }

        LegalStatus::findOrFail($newStatusCode);

        $previous = $case->legal_status_code;

        $case->update([
            'legal_status_code' => $newStatusCode,
            'status_determination_ref' => $determinationRef,
            'status_determination_authority' => $determinationAuthority,
            'status_determination_date' => $determinationDate,
        ]);

        $this->audit->write(
            $actor,
            'status.changed',
            'ImmigrationCase',
            $case->id,
            "dari={$previous}; ke={$newStatusCode}; rujukan={$determinationRef}",
        );
    }
}
