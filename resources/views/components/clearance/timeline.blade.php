@props(['timeline'])

<ol class="space-y-3" data-testid="clearance-timeline">
    @foreach ($timeline as $entry)
        @php($status = $entry['status'])
        <li class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10" data-stage="{{ $entry['stage']->key }}" data-status="{{ $status }}">
            <span class="mt-0.5 inline-flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-xs font-medium
                {{ match ($status) { 'approved' => 'bg-success-100 text-success-800', 'rejected' => 'bg-danger-100 text-danger-800', 'pending' => 'bg-warning-100 text-warning-800', 'skipped' => 'bg-gray-100 text-gray-600', default => 'bg-gray-50 text-gray-500' } }}">
                {{ __('erp.clearance.stage_statuses.'.$status) }}
            </span>
            <div class="min-w-0 text-sm">
                <div class="font-medium text-gray-950 dark:text-white">{{ $entry['stage']->label }}</div>
                @if ($entry['approver'])
                    <div class="text-gray-600 dark:text-gray-300">{{ $entry['approver'] }}@if ($entry['designation']) <span class="text-gray-500">— {{ $entry['designation'] }}</span>@endif</div>
                @endif
                @if ($entry['at'])
                    <div class="text-xs text-gray-500">{{ $entry['at']->format('d M Y H:i') }}</div>
                @endif
                @if ($entry['remarks'])
                    <div class="mt-1 text-gray-700 dark:text-gray-200" data-testid="stage-remarks">{{ $entry['remarks'] }}</div>
                @endif
            </div>
        </li>
    @endforeach
</ol>
