<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Review;
use App\Services\Exceptions\ReviewRequiredException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penutupan peringatan (FR10, subbagian 4.3.2).
 *
 * Menutup peringatan menuntut catatan keputusan beserta alasannya. Tanpa syarat
 * ini sistem hanya memberi informasi. Dengan syarat ini sistem menghasilkan
 * jejak pertanggungjawaban yang dapat diaudit.
 */
class AlertCloser
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @throws ReviewRequiredException
     */
    public function close(
        Alert $alert,
        Carbon $reviewedAt,
        string $reviewerRole,
        string $decision,
        ?string $reasoning,
        string $actor = 'system',
    ): Review {
        if (blank($reasoning) || blank($decision)) {
            $this->audit->write(
                $actor,
                'alert.close_refused',
                'Alert',
                $alert->id,
                'alasan=keputusan atau alasan peninjauan kosong',
            );

            throw new ReviewRequiredException(
                'Penutupan peringatan menuntut keputusan dan alasan peninjauan.'
            );
        }

        return DB::transaction(function () use ($alert, $reviewedAt, $reviewerRole, $decision, $reasoning, $actor) {
            $review = Review::create([
                'alert_id' => $alert->id,
                'reviewed_at' => $reviewedAt,
                'reviewer_role' => $reviewerRole,
                'decision' => $decision,
                'reasoning' => $reasoning,
            ]);

            $alert->update(['state' => 'closed']);

            $this->audit->write($actor, 'alert.closed', 'Alert', $alert->id, "keputusan={$decision}");

            return $review;
        });
    }
}
