<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $box->organizationalUnit->unit_name }} Receiving Box</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; background: #f1f5f9; color: #0f172a; }
        main { width: min(960px, calc(100% - 2rem)); margin: 2rem auto; }
        .header, .record, .empty, .claim { background: white; border: 1px solid #e2e8f0; border-radius: 1rem; }
        .header { padding: 1.5rem; margin-bottom: 1rem; }
        .eyebrow { margin: 0 0 .4rem; color: #0369a1; font-size: .78rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(1.45rem, 5vw, 2rem); }
        .meta { margin: .6rem 0 0; color: #475569; }
        .summary { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1rem; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: .35rem .75rem; background: #e0f2fe; color: #075985; font-size: .85rem; font-weight: 650; }
        .records { display: grid; gap: .75rem; }
        .record { display: flex; gap: .9rem; align-items: flex-start; padding: 1rem 1.15rem; cursor: pointer; }
        .record input { width: 1.2rem; height: 1.2rem; margin-top: .15rem; accent-color: #0369a1; }
        .record-content { flex: 1; min-width: 0; }
        .tracking { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 750; color: #0369a1; }
        .subject { margin: .4rem 0; font-weight: 650; }
        .details { margin: 0; color: #64748b; font-size: .9rem; }
        .empty { padding: 2rem; color: #475569; text-align: center; }
        .claim { margin-top: 1rem; padding: 1.25rem; }
        .claim h2 { margin: 0 0 .35rem; font-size: 1.15rem; }
        .claim-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem; }
        .field { display: grid; gap: .35rem; }
        .field.full { grid-column: 1 / -1; }
        .field label { font-size: .88rem; font-weight: 700; }
        .field input, .field textarea { box-sizing: border-box; width: 100%; border: 1px solid #cbd5e1; border-radius: .6rem; padding: .7rem .8rem; font: inherit; }
        .field input:focus, .field textarea:focus { border-color: #0284c7; outline: 3px solid #bae6fd; }
        .submit { margin-top: 1rem; border: 0; border-radius: .65rem; padding: .75rem 1rem; background: #0369a1; color: white; font: inherit; font-weight: 750; cursor: pointer; }
        .alert { margin-bottom: 1rem; border-radius: .75rem; padding: .85rem 1rem; background: #dcfce7; color: #166534; }
        .errors { margin-bottom: 1rem; border-radius: .75rem; padding: .85rem 1rem; background: #fee2e2; color: #991b1b; }
        .errors ul { margin: .35rem 0 0; padding-left: 1.25rem; }
        .notice { margin: 1rem 0 0; color: #64748b; font-size: .85rem; }
        a { color: #0369a1; }
        @media (max-width: 640px) { .claim-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <section class="header">
        <p class="eyebrow">Authenticated receiving-box inventory</p>
        <h1>{{ $box->organizationalUnit->unit_name }}</h1>
        <p class="meta">
            {{ $box->box_location ?: 'Physical location not recorded' }}
        </p>
        <div class="summary">
            <span class="badge">{{ $recipients->count() }} ready for pickup</span>
            <span class="badge">Signed in as {{ auth()->user()->full_name }}</span>
        </div>
        <p class="notice">
            QR scanning identifies the box; your signed-in account determines access.
            {{ $canClaim ? 'Select one or more records below to confirm physical receipt.' : 'This administrator preview is read-only.' }}
        </p>
    </section>

    @if (session('status'))
        <div class="alert" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors" role="alert">
            <strong>The confirmation could not be completed.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($recipients->isEmpty())
        <section class="empty">
            No documents are currently ready for pickup in this receiving box.
        </section>
    @else
        <form method="POST" action="{{ route('receiving-boxes.claim', ['qrToken' => $box->qr_token]) }}">
            @csrf
            <section class="records" aria-label="Documents ready for pickup">
                @foreach ($recipients as $recipient)
                    <label class="record">
                        @if ($canClaim)
                            <input
                                type="checkbox"
                                name="recipient_ids[]"
                                value="{{ $recipient->id }}"
                                @checked(in_array($recipient->id, old('recipient_ids', [])))
                            >
                        @endif
                        <span class="record-content">
                            <span class="tracking">{{ $recipient->document->tracking_no }}</span>
                            <span class="subject">{{ $recipient->document->subject }}</span>
                            <span class="details">
                                {{ $recipient->document->documentType->name }}
                                · Placed {{ $recipient->date_placed?->format('M j, Y g:i A') }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </section>

            @if ($canClaim)
                <section class="claim" aria-labelledby="claim-heading">
                    <h2 id="claim-heading">Confirm physical receipt</h2>
                    <p class="details">The signed-in user, organizational unit, selected records, time, and request context are derived by the server.</p>
                    <div class="claim-grid">
                        <div class="field">
                            <label for="receiver_name">Receiver name</label>
                            <input id="receiver_name" name="receiver_name" value="{{ old('receiver_name') }}" maxlength="255" required>
                        </div>
                        <div class="field">
                            <label for="receiver_position">Position or designation</label>
                            <input id="receiver_position" name="receiver_position" value="{{ old('receiver_position') }}" maxlength="255" required>
                        </div>
                        <div class="field full">
                            <label for="remarks">Remarks (optional)</label>
                            <textarea id="remarks" name="remarks" rows="3" maxlength="2000">{{ old('remarks') }}</textarea>
                        </div>
                    </div>
                    <button class="submit" type="submit">Confirm selected documents</button>
                </section>
            @endif
        </form>
    @endif

    <p class="notice"><a href="{{ url('/admin') }}">Return to DRMS administration</a></p>
</main>
</body>
</html>
