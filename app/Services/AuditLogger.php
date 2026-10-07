<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Log audit (NFR3).
 *
 * Pembacaan dicatat sama seperti penulisan, karena paparan data detensi muncul
 * dari akses, bukan hanya dari perubahan. Sifat hanya-tambah ditegakkan oleh
 * pemicu basis data, bukan oleh kelas ini.
 */
class AuditLogger
{
    public function write(
        string $actor,
        string $action,
        string $entity,
        int|string|null $entityId = null,
        ?string $detail = null,
    ): AuditLog {
        return AuditLog::create([
            'actor' => $actor,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'detail' => $detail,
        ]);
    }

    public function read(string $actor, string $entity, int|string|null $entityId = null): AuditLog
    {
        return $this->write($actor, 'read', $entity, $entityId);
    }
}
