<x-layouts::auth.card :title="__('Tidak Terdaftar')">
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-red-100 dark:bg-red-950">
            <svg class="size-7 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="m15 9-6 6M9 9l6 6" /></svg>
        </span>

        <div>
            <flux:heading size="xl" level="1">{{ __('Tidak Terdaftar') }}</flux:heading>
            <flux:subheading>{{ __('Akunmu tidak terdaftar sebagai peserta di sesi :session.', ['session' => $session->name]) }}</flux:subheading>
        </div>
    </div>
</x-layouts::auth.card>
