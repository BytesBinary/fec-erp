<div class="max-h-96 overflow-y-auto text-sm" data-testid="integration-activity">
    @forelse ($logs as $log)
        <div class="flex justify-between gap-3 border-b border-gray-100 py-2 dark:border-white/5">
            <span>{{ $log->after['tool'] ?? $log->action }}@if ($log->entity_type) <span class="text-gray-500">({{ $log->entity_type }} {{ $log->entity_id }})</span>@endif</span>
            <span class="{{ ($log->after['outcome'] ?? '') === 'success' ? 'text-success-600' : 'text-warning-600' }}">{{ $log->after['outcome'] ?? '' }}</span>
            <span class="text-gray-500">{{ $log->created_at->format('d M H:i') }}</span>
        </div>
    @empty
        <p class="text-gray-500">No activity yet.</p>
    @endforelse
</div>
