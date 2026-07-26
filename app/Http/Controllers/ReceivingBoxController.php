<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\OperationalStatus;
use App\Enums\RecipientStatus;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\ReceivingBoxClaimService;
use App\Services\ReceivingBoxQrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReceivingBoxController extends Controller
{
    public function inventory(string $qrToken): View
    {
        $box = $this->resolveActiveBox($qrToken);

        Gate::authorize('viewInventory', $box);

        $recipients = $box->recipients()
            ->with(['document.documentType'])
            ->where('recipient_unit_id', $box->organizational_unit_id)
            ->where('recipient_status', RecipientStatus::ReadyForPickup->value)
            ->whereNotNull('date_placed')
            ->whereHas('document', fn ($query) => $query
                ->where('current_status', '!=', DocumentStatus::Cancelled->value))
            ->orderByDesc('date_placed')
            ->get();
        $canClaim = Gate::allows('claimInventory', $box);

        return view('receiving-boxes.inventory', compact('box', 'recipients', 'canClaim'));
    }

    public function claim(
        Request $request,
        string $qrToken,
        ReceivingBoxClaimService $claims,
    ): RedirectResponse {
        $box = $this->resolveActiveBox($qrToken);
        Gate::authorize('claimInventory', $box);

        $validated = $request->validate([
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['required', 'integer', 'distinct'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_position' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $transactions = $claims->claim(
            actor: $actor,
            box: $box,
            recipientIds: $validated['recipient_ids'],
            receiverName: $validated['receiver_name'],
            receiverPosition: $validated['receiver_position'],
            remarks: $validated['remarks'] ?? null,
            context: [
                'ip_address' => $request->ip(),
                'device_info' => $request->userAgent(),
            ],
        );

        return redirect()
            ->route('receiving-boxes.inventory', ['qrToken' => $box->qr_token])
            ->with('status', sprintf(
                '%d document(s) confirmed as received.',
                $transactions->count(),
            ));
    }

    public function label(ReceivingBox $receivingBox, ReceivingBoxQrCodeService $qrCodes): View
    {
        Gate::authorize('view', $receivingBox);
        $receivingBox->load('organizationalUnit');

        return view('receiving-boxes.label', [
            'box' => $receivingBox,
            'inventoryUrl' => $qrCodes->inventoryUrl($receivingBox),
            'qrSvg' => $qrCodes->svg($receivingBox),
        ]);
    }

    private function resolveActiveBox(string $qrToken): ReceivingBox
    {
        return ReceivingBox::query()
            ->with('organizationalUnit')
            ->where('qr_token', $qrToken)
            ->where('status', OperationalStatus::Active->value)
            ->whereHas('organizationalUnit', fn ($query) => $query
                ->where('status', OperationalStatus::Active->value))
            ->firstOrFail();
    }
}
