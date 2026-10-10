<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Clearance verification</title>
    <style>
        :root { color-scheme: light dark; --ok: #0a6b2d; --bad: #b00020; --warn: #8a5a00; }
        body { font-family: system-ui, sans-serif; margin: 0; padding: 16px; background: Canvas; color: CanvasText; }
        main { max-width: 640px; margin: 0 auto; }
        .status { padding: 14px; border-radius: 8px; border: 2px solid; margin: 16px 0; font-weight: 600; }
        .ok { border-color: var(--ok); color: var(--ok); } .bad { border-color: var(--bad); color: var(--bad); } .warn { border-color: var(--warn); color: var(--warn); }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; }
        dt { font-weight: 600; }
        table { width: 100%; border-collapse: collapse; } th, td { text-align: left; padding: 6px; border-bottom: 1px solid GrayText; }
    </style>
</head>
<body>
<main>
    <h1>{{ $institution->institution_name }}</h1>
    <p>Clearance verification</p>

    @if (! $integrity['intact'])
        <div class="status bad" role="alert" data-testid="verification-status">WARNING: this record has been altered. Do not accept this clearance.</div>
    @elseif ($valid)
        <div class="status ok" data-testid="verification-status">Valid — all approvals are complete and the record is intact.</div>
    @elseif ($request->status === \App\Enums\ClearanceStatus::Cancelled)
        <div class="status bad" data-testid="verification-status">Cancelled — this clearance is not valid.</div>
    @else
        <div class="status warn" data-testid="verification-status">Not yet valid — approvals are still in progress.</div>
    @endif

    <dl>
        <dt>Student</dt><dd data-testid="verify-student">{{ $request->student->user->name }}</dd>
        <dt>Roll no.</dt><dd>{{ $request->student->roll_number }}</dd>
        <dt>Program</dt><dd>{{ $request->student->program?->name }}</dd>
        <dt>Request no.</dt><dd>{{ $request->request_no }}</dd>
        <dt>Status</dt><dd data-testid="verify-final-status">{{ $request->status->label() }}</dd>
        <dt>Record integrity</dt><dd data-testid="verify-integrity">{{ $integrity['intact'] ? 'Intact' : 'ALTERED' }}</dd>
    </dl>

    <h2>Approvals</h2>
    <table>
        <thead><tr><th>Stage</th><th>Date</th></tr></thead>
        <tbody>
        @foreach ($approvals as $approval)
            <tr><td>{{ $approval->stage->label }}</td><td>{{ $approval->decided_at->format('d M Y') }}</td></tr>
        @endforeach
        </tbody>
    </table>
</main>
</body>
</html>
