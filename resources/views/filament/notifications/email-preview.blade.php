<div class="space-y-3 text-sm" data-testid="email-preview">
    @if ($preview['warning'])
        <p class="rounded-lg border border-danger-300 p-2 text-danger-700" data-testid="preview-warning">{{ $preview['warning'] }}</p>
    @endif
    <div>
        <div class="text-gray-500">Subject</div>
        <div class="font-medium" data-testid="preview-subject">{{ $preview['subject'] }}</div>
    </div>
    <div>
        <div class="text-gray-500">Body</div>
        <pre class="whitespace-pre-wrap rounded-lg border border-gray-300 p-3 font-sans dark:border-gray-600" data-testid="preview-body">{{ $preview['body'] }}</pre>
    </div>
    <div class="text-gray-500">Placeholders you can use: @foreach ($placeholders as $placeholder)<code class="mr-1">{{ '{'.$placeholder.'}' }}</code>@endforeach</div>
</div>
