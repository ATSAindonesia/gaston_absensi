<?php

use App\Actions\Admin\GetAdminOverview;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Dashboard Admin')] class extends Component
{
    public string $lastRefreshedAt = '';

    /**
     * Authorize user access.
     */
    public function mount(): void
    {
        Gate::authorize('manage-sessions');

        $this->lastRefreshedAt = now()->format('d M Y H:i');
    }

    /**
     * Refresh dashboard data and update the timestamp.
     */
    public function refreshBoard(): void
    {
        Gate::authorize('manage-sessions');

        unset($this->stats, $this->activeSession, $this->upcomingSessions);

        $this->lastRefreshedAt = now()->format('d M Y H:i');
    }

    /**
     * Get admin overview statistics.
     *
     * @return array{total_sessions: int, total_users: int, total_participants: int}
     */
    #[Computed]
    public function stats(): array
    {
        return app(GetAdminOverview::class)->stats();
    }

    /**
     * Get the currently active session, if any.
     */
    #[Computed]
    public function activeSession()
    {
        return app(GetAdminOverview::class)->activeSession();
    }

    /**
     * Get upcoming (scheduled) sessions.
     */
    #[Computed]
    public function upcomingSessions()
    {
        return app(GetAdminOverview::class)->upcomingSessions();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Dashboard Admin') }}</flux:heading>
        <flux:subheading>{{ __('Ringkasan area admin: sesi, pengguna, dan sesi yang sedang aktif.') }}</flux:subheading>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
            <span data-test="last-updated">{{ __('Terakhir diperbarui: :time', ['time' => $lastRefreshedAt]) }}</span>
            <flux:button variant="ghost" size="sm" icon="arrow-path" wire:click="refreshBoard" data-test="refresh-board">
                {{ __('Segarkan') }}
            </flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Sesi') }}</div>
                    <div class="mt-1 text-3xl font-semibold text-zinc-900 tabular-nums dark:text-zinc-100" data-test="stat-sessions">{{ $this->stats['total_sessions'] }}</div>
                </div>
                <span class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-950 dark:text-blue-300" aria-hidden="true">
                    <flux:icon.calendar-days class="size-5" />
                </span>
            </div>
            <span class="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 bg-blue-500/70" aria-hidden="true"></span>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Pengguna') }}</div>
                    <div class="mt-1 text-3xl font-semibold text-zinc-900 tabular-nums dark:text-zinc-100" data-test="stat-users">{{ $this->stats['total_users'] }}</div>
                </div>
                <span class="rounded-lg bg-violet-100 p-2 text-violet-600 dark:bg-violet-950 dark:text-violet-300" aria-hidden="true">
                    <flux:icon.users class="size-5" />
                </span>
            </div>
            <span class="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 bg-violet-500/70" aria-hidden="true"></span>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Peserta Terdaftar') }}</div>
                    <div class="mt-1 text-3xl font-semibold text-zinc-900 tabular-nums dark:text-zinc-100" data-test="stat-participants">{{ $this->stats['total_participants'] }}</div>
                </div>
                <span class="rounded-lg bg-emerald-100 p-2 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300" aria-hidden="true">
                    <flux:icon.user-group class="size-5" />
                </span>
            </div>
            <span class="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 bg-emerald-500/70" aria-hidden="true"></span>
        </div>
    </div>

    @if ($this->activeSession)
        <div class="rounded-xl border border-green-200 bg-green-50 p-5 dark:border-green-900/50 dark:bg-green-950/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-green-700 dark:text-green-300">{{ __('Sesi Aktif') }}</div>
                    <div class="mt-1 text-lg font-semibold text-green-900 dark:text-green-100">{{ $this->activeSession->name }}</div>
                    <div class="text-sm text-green-800 dark:text-green-200">
                        {{ $this->activeSession->sport?->name ?? '-' }} · {{ $this->activeSession->court?->name ?? '-' }} ·
                        {{ $this->activeSession->starts_at->format('d M Y H:i') }} – {{ $this->activeSession->ends_at->format('H:i') }} ·
                        {{ __('Peserta: :count', ['count' => $this->activeSession->participants_count]) }}
                    </div>
                </div>
                <flux:button variant="primary" size="sm" :href="route('admin.sessions.show', $this->activeSession)" wire:navigate>
                    {{ __('Kelola Sesi') }}
                </flux:button>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Tidak ada sesi aktif. Buat dan mulai sesi dari halaman Kelola Sesi.') }}
                </p>
                <flux:button variant="primary" size="sm" icon="plus" :href="route('admin.sessions')" wire:navigate data-test="create-session-cta">
                    {{ __('Buat Sesi') }}
                </flux:button>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Sesi Mendatang') }}</flux:heading>
            <flux:subheading>{{ __('Jadwal terdekat yang belum dimulai.') }}</flux:subheading>

            <div class="mt-4 space-y-2">
                @forelse ($this->upcomingSessions as $upcoming)
                    <a href="{{ route('admin.sessions.show', $upcoming) }}" wire:navigate class="flex items-center justify-between gap-3 rounded-lg border border-neutral-200 p-3 hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-zinc-800" wire:key="upcoming-{{ $upcoming->id }}">
                        <div class="text-sm">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $upcoming->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $upcoming->court?->name ?? '-' }} · {{ $upcoming->starts_at->format('d M Y H:i') }}</div>
                        </div>
                        <flux:badge color="zinc" size="sm">{{ $upcoming->participants_count }} {{ __('peserta') }}</flux:badge>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('Belum ada sesi terjadwal.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Aksi Cepat') }}</flux:heading>
            <flux:subheading>{{ __('Navigasi ke halaman pengelolaan.') }}</flux:subheading>

            <div class="mt-4 flex flex-col gap-2">
                <flux:button variant="outline" icon="calendar-days" :href="route('admin.sessions')" wire:navigate>
                    {{ __('Kelola Sesi') }}
                </flux:button>
                <flux:button variant="outline" icon="users" :href="route('admin.users')" wire:navigate>
                    {{ __('Informasi Pengguna') }}
                </flux:button>
            </div>
        </div>
    </div>
</div>
