<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $box->organizationalUnit->unit_name }} Receiving Box Label</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; background: #e2e8f0; color: #0f172a; }
        .toolbar { display: flex; justify-content: center; gap: .75rem; padding: 1rem; }
        button, a { border: 0; border-radius: .6rem; padding: .7rem 1rem; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
        button { background: #0369a1; color: white; }
        a { background: white; color: #0f172a; }
        .sheet { box-sizing: border-box; width: min(148mm, calc(100% - 2rem)); min-height: 148mm; margin: 0 auto 2rem; padding: 12mm; background: white; border: 2px solid #0f172a; text-align: center; }
        .brand { margin: 0; color: #0369a1; font-size: 12pt; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 3mm 0 1mm; font-size: 22pt; line-height: 1.1; }
        .location { margin: 0 0 5mm; color: #475569; font-size: 12pt; }
        .qr svg { width: 78mm; height: 78mm; max-width: 100%; }
        .instruction { margin: 4mm 0 0; font-size: 13pt; font-weight: 750; }
        .security { margin: 2mm auto 0; max-width: 110mm; color: #475569; font-size: 9pt; }
        @media print {
            @page { size: A5; margin: 0; }
            body { background: white; }
            .toolbar { display: none; }
            .sheet { width: 148mm; min-height: 148mm; margin: 0; border: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print permanent label</button>
        <a href="{{ $inventoryUrl }}">Preview inventory</a>
    </div>

    <main class="sheet">
        <p class="brand">District Records Management System</p>
        <h1>{{ $box->organizationalUnit->unit_name }}</h1>
        <p class="location">{{ $box->box_location ?: 'Receiving Box' }}</p>
        <div class="qr">{!! $qrSvg !!}</div>
        <p class="instruction">Scan to view this receiving box</p>
        <p class="security">Authentication is required. The QR code identifies this box but does not authorize access or confirm document claims.</p>
    </main>
</body>
</html>
