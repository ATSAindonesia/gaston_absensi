<x-layouts::auth.card :title="__('Absensi Tercatat')">
    <div class="flex flex-col items-center gap-4 text-center">
        <span class="flex size-14 items-center justify-center rounded-full bg-green-100 dark:bg-green-950">
            <svg class="size-7 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
        </span>

        <div>
            <flux:heading size="xl" level="1">{{ $already ? __('Sudah Tercatat') : __('Kehadiran Tercatat') }}</flux:heading>
            <flux:subheading>
                @if ($already)
                    {{ __('Kamu sudah absen di sesi ini sebelumnya.') }}
                @else
                    {{ __('Terima kasih, kehadiranmu berhasil dicatat.') }}
                @endif
            </flux:subheading>
        </div>

        <dl class="w-full space-y-2 rounded-xl bg-neutral-50 p-4 text-left text-sm dark:bg-white/5">
            <div class="flex justify-between gap-3">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Sesi') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $session->name }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Admin') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $session->creator?->name ?? '-' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Waktu Absen') }}</dt>
                <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $attendance->scanned_at->format('d M Y H:i:s') }}</dd>
            </div>
        </dl>
    </div>
</x-layouts::auth.card>
