<x-layouts::auth.card :title="__('QR Kedaluwarsa')">
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950">
            <svg class="size-7 text-amber-600 dark:text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>
        </span>

        <div>
            <flux:heading size="xl" level="1">{{ __('QR Kedaluwarsa') }}</flux:heading>
            <flux:subheading>{{ __('Kode QR ini sudah berganti. Pindai ulang QR yang tampil saat ini.') }}</flux:subheading>
        </div>
    </div>
</x-layouts::auth.card>
