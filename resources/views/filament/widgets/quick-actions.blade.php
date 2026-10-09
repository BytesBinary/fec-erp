<x-filament-widgets::widget>
    <x-filament::section heading="Quick Actions">
        <div class="flex flex-wrap gap-3">
            @foreach($this->getActions() as $action)
                <x-filament::button
                    tag="a"
                    :href="$action['url']"
                    :icon="$action['icon']"
                    color="gray"
                    outlined
                >
                    {{ $action['label'] }}
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
