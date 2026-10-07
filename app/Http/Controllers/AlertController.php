<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Services\AlertCloser;
use App\Services\Exceptions\ReviewRequiredException;
use App\Support\ReferenceDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Peninjauan dan penutupan peringatan (FR10).
 */
class AlertController extends Controller
{
    public function close(Request $request, Alert $alert, AlertCloser $closer): RedirectResponse
    {
        // Keputusan dan alasan sengaja tidak diwajibkan oleh validasi formulir.
        // Kewajiban itu ditegakkan AlertCloser, sehingga penolakan tercatat pada
        // log audit, sama seperti penolakan dari jalur lain.
        $data = $request->validate([
            'decision' => ['nullable', 'string', 'max:255'],
            'reasoning' => ['nullable', 'string', 'max:5000'],
            'reviewed_at' => ['nullable', 'date'],
        ]);

        $back = redirect()->route('cases.show', $alert->immigration_case_id);

        if ($alert->state !== 'open') {
            return $back->with('error', 'Peringatan sudah ditutup.');
        }

        try {
            $closer->close(
                alert: $alert,
                reviewedAt: isset($data['reviewed_at']) ? Carbon::parse($data['reviewed_at']) : ReferenceDate::resolve(),
                reviewerRole: $request->user()->role,
                decision: (string) ($data['decision'] ?? ''),
                reasoning: $data['reasoning'] ?? null,
                actor: $request->user()->auditActor(),
            );
        } catch (ReviewRequiredException $e) {
            return $back->with('error', $e->getMessage())->withInput();
        }

        return $back->with('status', "Peringatan {$alert->indicator_code} ditutup dengan catatan peninjauan.");
    }
}
