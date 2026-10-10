<x-filament-panels::page>
    @if ($this->isRequiredByPolicy() && ! $this->isEnabled())
        <x-filament::section>
            <p class="text-sm text-warning-600" data-testid="mfa-required-notice">{{ __('erp.security.setup_required') }}</p>
        </x-filament::section>
    @endif

    @if (count($recoveryCodes) > 0)
        <x-filament::section :heading="__('erp.security.recovery_codes_heading')" :description="__('erp.security.recovery_codes_help')">
            <div class="space-y-4" data-testid="recovery-codes-panel">
                <ul class="grid grid-cols-2 gap-2 font-mono text-sm" data-testid="recovery-codes">
                    @foreach ($recoveryCodes as $code)
                        <li>{{ $code }}</li>
                    @endforeach
                </ul>
                <div class="flex flex-wrap gap-3">
                    <a class="fi-btn fi-btn-size-md fi-color-gray fi-btn-color-gray text-sm underline" download="recovery-codes.txt"
                       href="data:text/plain;charset=utf-8,{{ rawurlencode(implode("\n", $recoveryCodes)."\n") }}">{{ __('erp.security.download') }}</a>
                    <button type="button" class="text-sm underline"
                            x-on:click="const w = window.open('', '_blank'); w.document.write('<pre>' + @js(implode("\n", $recoveryCodes)) + '</pre>'); w.document.close(); w.print();">
                        {{ __('erp.security.print') }}
                    </button>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="savedRecoveryCodes" id="saved-recovery-codes" class="rounded border-gray-300" />
                    {{ __('erp.security.saved_codes_checkbox') }}
                </label>
                <x-filament::button wire:click="finishRecoveryCodes" :disabled="! $savedRecoveryCodes" id="finish-recovery-codes">
                    {{ __('erp.security.done') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @elseif ($this->isEnabled())
        <x-filament::section :heading="__('erp.security.enabled_heading')">
            <div class="space-y-4">
                <p class="text-sm" data-testid="mfa-status">{{ __('erp.security.enabled_help') }}</p>
                @php($remaining = $this->remainingRecoveryCodes())
                <p class="text-sm" data-testid="recovery-remaining">{{ __('erp.security.recovery_remaining', ['count' => $remaining]) }}</p>
                @if ($remaining < config('security.two_factor.recovery_warn_below'))
                    <p class="text-sm text-warning-600" data-testid="recovery-warning">{{ __('erp.security.recovery_low') }}</p>
                @endif
                <div class="flex flex-wrap gap-3">
                    {{ $this->regenerateAction }}
                    {{ $this->disableAction }}
                </div>
            </div>
        </x-filament::section>
    @elseif ($settingUp)
        <x-filament::section :heading="__('erp.security.scan_heading')" :description="__('erp.security.scan_help')">
            <div class="space-y-4">
                <img src="{{ $this->qrCodeDataUri() }}" alt="{{ __('erp.security.qr_alt') }}" class="h-48 w-48 bg-white" data-testid="mfa-qr" />
                <p class="text-sm">{{ __('erp.security.manual_key') }}: <code class="font-mono" data-testid="mfa-secret">{{ $this->setupSecret() }}</code></p>
                <form wire:submit.prevent="confirmSetup" class="space-y-3">
                    <label class="block text-sm font-medium" for="confirmation-code">{{ __('erp.security.confirm_code_label') }}</label>
                    <input id="confirmation-code" type="text" inputmode="numeric" autocomplete="one-time-code" wire:model="confirmationCode"
                           class="fi-input block w-48 rounded-lg border-gray-300 text-sm" />
                    @error('confirmationCode') <p class="text-sm text-danger-600" data-testid="confirmation-error">{{ $message }}</p> @enderror
                    <div class="flex gap-3">
                        <x-filament::button type="submit" id="confirm-two-factor">{{ __('erp.security.enable') }}</x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="cancelSetup">{{ __('erp.security.cancel') }}</x-filament::button>
                    </div>
                </form>
            </div>
        </x-filament::section>
    @else
        <x-filament::section :heading="__('erp.security.disabled_heading')" :description="__('erp.security.disabled_help')">
            {{ $this->startSetupAction }}
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
