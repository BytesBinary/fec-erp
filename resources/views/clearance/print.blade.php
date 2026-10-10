<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clearance {{ $request->request_no }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, system-ui, sans-serif; color: #111; font-size: 12px; margin: 0; padding: 0; }
        .sheet { position: relative; max-width: 190mm; margin: 0 auto; padding: 8mm; }
        .head { text-align: center; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .head img.logo { height: 56px; }
        h1 { font-size: 20px; margin: 4px 0; } h2 { font-size: 15px; margin: 0; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 5px 6px; vertical-align: middle; text-align: left; }
        th { background: #eee; }
        .meta { width: 100%; margin: 10px 0; }
        .meta td { border: 0; padding: 2px 4px; }
        .photo { width: 90px; height: 115px; border: 1px solid #555; object-fit: cover; }
        .photo-empty { width: 90px; height: 115px; border: 1px dashed #555; text-align: center; font-size: 10px; padding-top: 48px; color: #777; }
        .sig { height: 38px; max-width: 130px; }
        .qr { width: 90px; height: 90px; }
        .boxes { width: 100%; margin-top: 18px; }
        .boxes td { border: 0; vertical-align: bottom; }
        .principal-box { border: 1px solid #111; height: 70px; width: 220px; }
        .seal { border: 1px dashed #555; border-radius: 50%; height: 86px; width: 86px; text-align: center; font-size: 10px; color: #777; padding-top: 34px; }
        .footer { margin-top: 14px; font-size: 10px; text-align: center; color: #333; border-top: 1px solid #999; padding-top: 6px; }
        .watermark { position: absolute; top: 280px; left: 10%; font-size: 90px; color: rgba(200, 0, 0, 0.18); transform: rotate(-30deg); letter-spacing: 6px; pointer-events: none; }
        .toolbar { padding: 10px; background: #f3f4f6; text-align: center; font-family: system-ui, sans-serif; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
@php($rasterOk = ! ($pdfMode ?? false) || extension_loaded('gd'))
@unless ($pdfMode ?? false)
    <div class="toolbar no-print">
        @if ($preview)
            <strong data-testid="preview-banner">Preview — not an issued copy.</strong>
        @else
            <strong data-testid="issued-banner">{{ $duplicate ? 'DUPLICATE copy' : 'Original copy' }}</strong>
        @endif
        <button type="button" onclick="window.print()">Print</button>
        <a href="javascript:history.back()">Back</a>
    </div>
@endunless
<div class="sheet" data-testid="clearance-sheet">
    @if ($duplicate)
        <div class="watermark" data-testid="duplicate-watermark">DUPLICATE</div>
    @endif

    <div class="head">
        @if ($logo && $rasterOk)<img class="logo" src="{{ $logo }}" alt="">@endif
        <h1>{{ $institution->institution_name }}</h1>
        @if ($institution->address)<div>{{ $institution->address }}</div>@endif
        <h2>CLEARANCE CERTIFICATE</h2>
    </div>

    <table class="meta">
        <tr>
            <td style="width: 70%">
                <table class="meta">
                    <tr><td><strong>Request no.</strong></td><td data-testid="print-request-no">{{ $request->request_no }}</td></tr>
                    <tr><td><strong>Issue date</strong></td><td>{{ $issued_on }}</td></tr>
                    <tr><td><strong>Student</strong></td><td data-testid="print-student">{{ $student->profile?->full_name_certificate ?? $student->user->name }}</td></tr>
                    <tr><td><strong>Roll no.</strong></td><td>{{ $student->roll_number }}</td></tr>
                    <tr><td><strong>Registration</strong></td><td>{{ $student->registration_number }}</td></tr>
                    <tr><td><strong>Department</strong></td><td>{{ $student->department?->name }}</td></tr>
                    <tr><td><strong>Program</strong></td><td>{{ $student->program?->name }}</td></tr>
                    <tr><td><strong>Session</strong></td><td>{{ $student->batch?->session }}</td></tr>
                </table>
            </td>
            <td style="text-align: right">
                @if ($photo && $rasterOk)<img class="photo" src="{{ $photo }}" alt="Student photo">@else<div class="photo-empty" style="margin-left:auto">Photo</div>@endif
            </td>
        </tr>
    </table>

    <table data-testid="approval-table">
        <thead><tr><th>Stage</th><th>Approved by</th><th>Signature</th><th>Date and time</th></tr></thead>
        <tbody>
        @foreach ($stages as $row)
            <tr data-testid="approval-row">
                <td>{{ $row['stage'] }}</td>
                @if ($row['decision'] === 'skipped')
                    <td colspan="3" style="color:#666">Not applicable</td>
                @else
                    <td>{{ $row['name'] }}<br><small>{{ $row['designation'] }}</small></td>
                    <td>@if ($row['signature'] && $rasterOk)<img class="sig" data-testid="signature-image" src="{{ $row['signature'] }}" alt="Signature of {{ $row['name'] }}">@elseif ($row['signature'])<small>(signature on file)</small>@endif</td>
                    <td>{{ $row['decided_at']->format('d M Y H:i') }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="boxes">
        <tr>
            <td style="width: 34%"><img class="qr" data-testid="qr-code" data-verify-url="{{ $verification_url }}" src="{{ $qr }}" alt="QR code for verification"><br><small>Scan to verify</small></td>
            <td style="width: 36%"><div class="seal" data-testid="seal-area">Seal</div></td>
            <td style="width: 30%; text-align: right"><div class="principal-box" data-testid="principal-box" style="margin-left:auto"></div><div>{{ $principal_title }}{{ $principal_name ? ' — '.$principal_name : '' }}</div></td>
        </tr>
    </table>

    <div class="footer">Digitally approved by the authorities above. Valid only with the Principal's signature and the official seal.</div>
</div>
@if (($autoprint ?? false) && ! ($pdfMode ?? false))
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
