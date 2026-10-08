<?php

use App\Actions\Session\CancelSession;
use App\Actions\Session\CreateSession;
use App\Actions\Session\DeleteSession;
use App\Actions\Session\FinishSession;
use App\Actions\Session\ResolveSessionCourts;
use App\Actions\Session\StartSession;
use App\Actions\Session\UpdateSession;
use App\Exceptions\DomainException;
use App\Models\AttendanceSession;
use App\Models\Sport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Kelola Sesi')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $sessionId = null;

    public string $name = '';

    public ?int $sport_id = null;

    public ?int $court_id = null;

    public string $session_date = '';

    public string $start_time = '';

    public string $end_time = '';

    public ?string $pickerTarget = null;

    public string $pickerHour = '08';

    public string $pickerMinute = '00';

    public ?int $cancelTargetId = null;

    public string $cancel_reason = '';

    public ?int $deleteTargetId = null;

    /**
     * Authorize user access.
     */
    public function mount(): void
    {
        Gate::authorize('manage-sessions');
    }

    /**
     * Reset pagination when filters change.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Reset the court when the sport changes (courts belong to a sport).
     */
    public function updatedSportId(): void
    {
        $this->court_id = null;
    }

    /**
     * Open the create form.
     */
    public function openCreate(): void
    {
        Gate::authorize('manage-sessions');

        $this->reset(['sessionId', 'name', 'sport_id', 'court_id', 'session_date', 'start_time', 'end_time', 'pickerTarget', 'pickerHour', 'pickerMinute']);
        $this->resetValidation();
    }

    /**
     * Open the edit form for a session.
     */
    public function openEdit(int $id): void
    {
        Gate::authorize('manage-sessions');

        $session = AttendanceSession::findOrFail($id);

        if (! $this->authorizeRoomOwnership($session)) {
            return;
        }

        if ($session->status->isTerminal()) {
            Flux::toast(variant: 'danger', text: __('Sesi yang sudah selesai atau dibatalkan tidak dapat diubah.'));

            return;
        }

        $this->sessionId = $session->id;
        $this->name = $session->name;
        $this->sport_id = $session->sport_id;
        $this->court_id = $session->court_id;
        $this->session_date = $session->starts_at->format('Y-m-d');
        $this->start_time = $session->starts_at->format('H:i');
        $this->end_time = $session->ends_at->format('H:i');
        $this->resetValidation();
    }

    /**
     * Create or update a session. New sessions are always created as "soon".
     * Creation is blocked while another session is active (syarat 1 sesi aktif).
     */
    public function saveSession(): void
    {
        Gate::authorize('manage-sessions');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'court_id' => ['required', 'integer', Rule::exists('courts', 'id')->where('sport_id', $this->sport_id)],
            'session_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ]);

        $startsAt = $validated['session_date'].' '.$validated['start_time'];
        $endsAt = $validated['session_date'].' '.$validated['end_time'];

        if ($endsAt <= $startsAt) {
            $this->addError('end_time', __('Jam selesai harus setelah jam mulai.'));

            return;
        }

        if ($this->sessionId === null && AttendanceSession::active()->exists()) {
            Flux::toast(variant: 'danger', text: __('Masih ada sesi aktif. Selesaikan atau batalkan sesi yang berjalan sebelum membuat sesi baru.'));

            return;
        }

        try {
            if ($this->sessionId !== null) {
                $session = AttendanceSession::findOrFail($this->sessionId);

                if (! $this->authorizeRoomOwnership($session)) {
                    return;
                }

                app(UpdateSession::class)->handle($session, [
                    'name' => $validated['name'],
                    'sport_id' => $validated['sport_id'],
                    'court_id' => $validated['court_id'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);

                Flux::toast(variant: 'success', text: __('Sesi berhasil diperbarui.'));
            } else {
                app(CreateSession::class)->handle([
                    'name' => $validated['name'],
                    'sport_id' => $validated['sport_id'],
                    'court_id' => $validated['court_id'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ], Auth::user());

                Flux::toast(variant: 'success', text: __('Sesi berhasil dibuat dengan status Segera.'));
            }
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::modal('session-form')->close();

        $this->reset(['sessionId', 'name', 'sport_id', 'court_id', 'session_date', 'start_time', 'end_time', 'pickerTarget', 'pickerHour', 'pickerMinute', 'cancel_reason']);
    }

    /**
     * Prepare delete confirmation for a session.
     */
    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-sessions');

        $session = AttendanceSession::findOrFail($id);

        if (! $this->authorizeRoomOwnership($session)) {
            return;
        }

        $this->deleteTargetId = $id;
        $this->modal('session-delete')->show();
    }

    /**
     * Delete a session. Active sessions must be finished/cancelled first.
     */
    public function deleteSession(): void
    {
        Gate::authorize('manage-sessions');

        $session = AttendanceSession::findOrFail($this->deleteTargetId);

        if (! $this->authorizeRoomOwnership($session)) {
            $this->reset(['deleteTargetId']);

            return;
        }

        try {
            app(DeleteSession::class)->handle($session);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            Flux::modal('session-delete')->close();
            $this->reset(['deleteTargetId']);

            return;
        }

        Flux::modal('session-delete')->close();
        $this->reset(['deleteTargetId']);

        Flux::toast(variant: 'success', text: __('Sesi berhasil dihapus.'));
    }

    /**
     * Start a scheduled session. Only one active session is allowed.
     */
    public function startSession(int $id): void
    {
        Gate::authorize('manage-sessions');

        $session = AttendanceSession::findOrFail($id);

        if (! $this->authorizeRoomOwnership($session)) {
            return;
        }

        try {
            app(StartSession::class)->handle($session);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Sesi dimulai. Sekarang berstatus Aktif.'));
    }

    /**
     * Finish the active session.
     */
    public function finishSession(int $id): void
    {
        Gate::authorize('manage-sessions');

        $session = AttendanceSession::findOrFail($id);

        if (! $this->authorizeRoomOwnership($session)) {
            return;
        }

        try {
            app(FinishSession::class)->handle($session);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Sesi diselesaikan. Anda dapat membuat sesi baru.'));
    }

    /**
     * Prepare cancel confirmation (wajib modal konfirmasi sebelum pembatalan).
     */
    public function openCancel(int $id): void
    {
        Gate::authorize('manage-sessions');

        $session = AttendanceSession::findOrFail($id);

        if (! $this->authorizeRoomOwnership($session)) {
            return;
        }

        if (! $session->canBeCancelled()) {
            Flux::toast(variant: 'danger', text: __('Sesi ini tidak dapat dibatalkan.'));

            return;
        }

        $this->cancelTargetId = $session->id;
        $this->cancel_reason = '';
        $this->resetValidation();
        $this->modal('session-cancel')->show();
    }

    /**
     * Cancel a scheduled/active session. After cancellation a new session can be created.
     */
    public function cancelSession(): void
    {
        Gate::authorize('manage-sessions');

        $validated = $this->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $session = AttendanceSession::findOrFail($this->cancelTargetId);

        if (! $this->authorizeRoomOwnership($session)) {
            $this->reset(['cancelTargetId', 'cancel_reason']);

            return;
        }

        try {
            app(CancelSession::class)->handle($session, $validated['cancel_reason'] ?? null);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            $this->reset(['cancelTargetId', 'cancel_reason']);

            return;
        }

        $this->reset(['cancelTargetId', 'cancel_reason']);

        Flux::modal('session-cancel')->close();

        Flux::toast(variant: 'success', text: __('Sesi dibatalkan. Anda dapat membuat sesi baru.'));
    }

    /**
     * Determine if the current user can manage the given room (for Blade).
     * The rule itself lives in SessionPolicy; this only exposes it to the view.
     */
    public function canManageRoom(AttendanceSession $session): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('manage', $session);
    }

    /**
     * Deny with a toast when the current user may not manage the room.
     */
    public function authorizeRoomOwnership(AttendanceSession $session): bool
    {
        if ($this->canManageRoom($session)) {
            return true;
        }

        Flux::toast(variant: 'danger', text: __('Room ini milik admin lain dan hanya dapat dilihat (read-only).'));

        return false;
    }

    /**
     * Get hour options (00-23) for the time picker.
     *
     * @return list<string>
     */
    #[Computed]
    public function hours(): array
    {
        $hours = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hours[] = sprintf('%02d', $hour);
        }

        return $hours;
    }

    /**
     * Get minute options (00-59) for the time picker.
     *
     * @return list<string>
     */
    #[Computed]
    public function minutes(): array
    {
        $minutes = [];

        for ($minute = 0; $minute < 60; $minute++) {
            $minutes[] = sprintf('%02d', $minute);
        }

        return $minutes;
    }

    /**
     * Open the time picker modal for start or end time.
     */
    public function openTimePicker(string $target): void
    {
        Gate::authorize('manage-sessions');

        $this->pickerTarget = $target === 'end' ? 'end' : 'start';

        $current = $this->pickerTarget === 'end' ? $this->end_time : $this->start_time;

        if (preg_match('/^(\d{2}):(\d{2})$/', $current, $matches) === 1) {
            $this->pickerHour = $matches[1];
            $this->pickerMinute = $matches[2];
        } else {
            $this->pickerHour = '08';
            $this->pickerMinute = '00';
        }

        $this->modal('time-picker')->show();
    }

    /**
     * Apply the picked hour and minute to the targeted time field.
     */
    public function confirmTimePicker(): void
    {
        Gate::authorize('manage-sessions');

        $picked = sprintf('%02d:%02d', (int) $this->pickerHour, (int) $this->pickerMinute);

        if ($this->pickerTarget === 'end') {
            $this->end_time = $picked;
        } else {
            $this->start_time = $picked;
        }

        $this->reset(['pickerTarget']);

        Flux::modal('time-picker')->close();
    }

    /**
     * Get the currently active session, if any.
     */
    #[Computed]
    public function activeSession()
    {
        return AttendanceSession::with(['sport', 'court'])->active()->latest()->first();
    }

    /**
     * Get all sports for the form dropdown.
     */
    #[Computed]
    public function sports()
    {
        return Sport::orderBy('name')->get();
    }

    /**
     * Get courts of the selected sport for the form dropdown.
     */
    #[Computed]
    public function courts()
    {
        return app(ResolveSessionCourts::class)->handle($this->sport_id);
    }

    /**
     * Get paginated sessions with relations.
     */
    #[Computed]
    public function sessions()
    {
        return AttendanceSession::with(['creator', 'sport', 'court', 'participants'])
            ->withCount('participants')
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('court', fn ($courtQuery) => $courtQuery->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('sport', fn ($sportQuery) => $sportQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Kelola Sesi') }}</flux:heading>
            <flux:subheading>{{ __('Kelola room sendiri; room admin lain read-only. Hanya 1 sesi aktif dalam satu waktu.') }}</flux:subheading>
        </div>

        <flux:modal.trigger name="session-form">
            <flux:button variant="primary" icon="plus" wire:click="openCreate" data-test="create-session-button">
                {{ __('Buat Sesi') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->activeSession)
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-200">
            <span class="font-semibold">{{ __('Sesi aktif:') }}</span>
            {{ $this->activeSession->name }} — {{ $this->activeSession->court?->name ?? '-' }}
            ({{ $this->activeSession->starts_at->format('d M Y H:i') }} – {{ $this->activeSession->ends_at->format('H:i') }})
            <span class="mt-1 block text-xs">{{ __('Selesaikan atau batalkan sesi ini sebelum membuat sesi baru.') }}</span>
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="w-full max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari nama, cabor, atau lapangan...') }}" icon="magnifying-glass" />
        </div>
        <div class="flex gap-2">
            <flux:select wire:model.live="statusFilter" placeholder="{{ __('Semua Status') }}">
                <flux:select.option value="">{{ __('Semua Status') }}</flux:select.option>
                <flux:select.option value="soon">{{ __('Segera') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Aktif') }}</flux:select.option>
                <flux:select.option value="finished">{{ __('Selesai') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Dibatalkan') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="flex flex-col gap-4">
        @forelse ($this->sessions as $s)
            @php($canManage = $this->canManageRoom($s))
            @php($isOwner = (int) $s->created_by === (int) auth()->id())
            @php($statusColor = $s->status === \App\Enums\SessionStatus::Soon ? 'amber' : $s->status->color())
            {{-- Session card: header (title + contextual badges) / body (icon metadata grid) / footer (action bar). --}}
            <article aria-labelledby="session-title-{{ $s->id }}" class="flex flex-col gap-4 rounded-2xl border border-neutral-200 bg-white p-5 dark:border-white/10 dark:bg-ash" wire:key="session-card-{{ $s->id }}">
                {{-- Header zone: title + ownership pill, contextual status/type badges share one anchor. --}}
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <h3 id="session-title-{{ $s->id }}" class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                            <a href="{{ route('admin.sessions.show', $s) }}" wire:navigate class="underline-offset-2 hover:underline">
                                {{ $s->name }}
                            </a>
                        </h3>
                        @if ($canManage && $isOwner)
                            <flux:badge color="blue" size="sm">{{ __('Room Saya') }}</flux:badge>
                        @elseif (! $canManage)
                            <flux:badge color="zinc" size="sm">{{ __('Read-only · Milik :name', ['name' => $s->creator?->name ?? '-']) }}</flux:badge>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <flux:badge color="{{ $statusColor }}" size="sm">{{ $s->status->label() }}</flux:badge>
                        <flux:badge color="zinc" size="sm">{{ $s->sport?->name ?? '-' }}</flux:badge>
                    </div>
                </header>

                {{-- Body zone: cohesive icon-label-value metadata grid (2 columns on sm+). --}}
                <dl class="grid grid-cols-1 gap-3 border-t border-neutral-100 pt-4 sm:grid-cols-2 dark:border-white/10">
                    <div class="flex items-start gap-2.5">
                        <svg class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" /><circle cx="12" cy="10" r="3" /></svg>
                        <div class="min-w-0">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Lapangan') }}</dt>
                            <dd class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $s->court?->name ?? '-' }} · {{ $s->sport?->name ?? '-' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <svg class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" /><path d="M16 2v4M8 2v4M3 10h18" /></svg>
                        <div class="min-w-0">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Jam Sesi') }}</dt>
                            <dd class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $s->starts_at->format('d M Y H:i') }} – {{ $s->ends_at->format('H:i') }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <svg class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                        <div class="min-w-0">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Nama Admin') }}</dt>
                            <dd class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $s->creator?->name ?? '-' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <svg class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                        <div class="min-w-0">
                            <dt class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Peserta') }}</dt>
                            <dd class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __(':count orang', ['count' => $s->participants_count]) }}</dd>
                        </div>
                    </div>
                </dl>

                {{-- Footer zone: max 2 visible buttons (primary contextual + secondary) + overflow menu for destructive actions. --}}
                <footer class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-100 pt-3 dark:border-white/10">
                    @if ($canManage && $s->status === \App\Enums\SessionStatus::Soon)
                        {{-- Primary state action: solid, stands out. --}}
                        <flux:button size="sm" variant="primary" wire:click="startSession({{ $s->id }})" data-test="start-{{ $s->id }}">{{ __('Mulai') }}</flux:button>
                    @endif

                    @if ($canManage && $s->status === \App\Enums\SessionStatus::Active)
                        {{-- Primary state action: solid, stands out. --}}
                        <flux:button size="sm" variant="primary" wire:click="finishSession({{ $s->id }})" data-test="finish-{{ $s->id }}">{{ __('Selesaikan') }}</flux:button>
                    @endif

                    {{-- Secondary action: low-emphasis ghost. --}}
                    <flux:button size="sm" variant="ghost" :href="route('admin.sessions.show', $s)" wire:navigate>{{ $canManage ? __('Kelola') : __('Lihat') }}</flux:button>

                    @if ($canManage && $s->status === \App\Enums\SessionStatus::Soon)
                        {{-- Secondary action: low-emphasis ghost. --}}
                        <flux:modal.trigger name="session-form">
                            <flux:button size="sm" variant="ghost" wire:click="openEdit({{ $s->id }})">{{ __('Ubah') }}</flux:button>
                        </flux:modal.trigger>
                    @endif

                    @if ($canManage && ($s->status === \App\Enums\SessionStatus::Soon || $s->status === \App\Enums\SessionStatus::Active))
                        {{-- Destructive actions live in the overflow menu; each still opens its confirmation modal. --}}
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Opsi lain untuk :name', ['name' => $s->name]) }}" />
                            <flux:menu>
                                <flux:menu.item wire:click="openCancel({{ $s->id }})" icon="no-symbol" data-test="cancel-open-{{ $s->id }}">{{ __('Batalkan sesi') }}</flux:menu.item>
                                @if ($s->status !== \App\Enums\SessionStatus::Active)
                                    <flux:menu.item wire:click="confirmDelete({{ $s->id }})" icon="trash">{{ __('Hapus sesi') }}</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    @elseif ($canManage && $s->status !== \App\Enums\SessionStatus::Active)
                        <flux:dropdown align="end">
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Opsi lain untuk :name', ['name' => $s->name]) }}" />
                            <flux:menu>
                                <flux:menu.item wire:click="confirmDelete({{ $s->id }})" icon="trash">{{ __('Hapus sesi') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </footer>
            </article>
        @empty
            <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-white/10 dark:bg-ash dark:text-zinc-400">
                {{ __('Belum ada sesi. Buat sesi pertama.') }}
            </div>
        @endforelse
    </div>

    <div class="mt-2">
        {{ $this->sessions->links() }}
    </div>

    <!-- Modal form create/edit sesi -->
    <flux:modal name="session-form" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $sessionId ? __('Ubah Sesi') : __('Buat Sesi Baru') }}</flux:heading>
                <flux:subheading>{{ __('Sesi baru selalu berstatus Segera. Mulai manual agar menjadi Aktif.') }}</flux:subheading>
            </div>

            <form wire:submit="saveSession" class="space-y-4">
                <flux:input wire:model="name" :label="__('Nama Sesi')" required placeholder="{{ __('Latihan rutin Jumat') }}" />

                <flux:select wire:model.live="sport_id" :label="__('Cabang Olahraga')" required>
                    <flux:select.option value="">{{ __('Pilih cabor...') }}</flux:select.option>
                    @foreach ($this->sports as $sportOption)
                        <flux:select.option value="{{ $sportOption->id }}">{{ $sportOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="court_id" :label="__('Lapangan')" required>
                    <flux:select.option value="">{{ $sport_id === null ? __('Pilih cabor dulu...') : __('Pilih lapangan...') }}</flux:select.option>
                    @foreach ($this->courts as $courtOption)
                        <flux:select.option value="{{ $courtOption->id }}">{{ $courtOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="session_date" :label="__('Tanggal Sesi')" type="date" required :min="now()->format('Y-m-d')" />

                <div class="flex gap-2">
                    <div class="flex-1 space-y-1">
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Jam Mulai (24 jam)') }}</span>
                        <flux:button variant="ghost" wire:click="openTimePicker('start')" data-test="open-start-picker" class="w-full justify-center border border-neutral-200 font-mono text-base dark:border-neutral-700">
                            {{ $start_time !== '' ? $start_time : __('Pilih jam...') }}
                        </flux:button>
                    </div>

                    <div class="flex-1 space-y-1">
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Jam Selesai (24 jam)') }}</span>
                        <flux:button variant="ghost" wire:click="openTimePicker('end')" data-test="open-end-picker" class="w-full justify-center border border-neutral-200 font-mono text-base dark:border-neutral-700">
                            {{ $end_time !== '' ? $end_time : __('Pilih jam...') }}
                        </flux:button>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" data-test="save-session-button">
                        {{ __('Simpan') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal pemilih jam (24 jam, scroll) -->
    <flux:modal name="time-picker" class="sm:max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $pickerTarget === 'end' ? __('Pilih Jam Selesai') : __('Pilih Jam Mulai') }}</flux:heading>
                <flux:subheading>{{ __('Geser daftar jam dan menit (format 24 jam).') }}</flux:subheading>
            </div>

            <div class="flex items-center justify-center rounded-xl bg-neutral-100 py-3 dark:bg-zinc-800">
                <span class="font-mono text-3xl font-semibold text-zinc-900 tabular-nums dark:text-zinc-100" aria-live="polite">{{ $pickerHour }}:{{ $pickerMinute }}</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <div class="text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Jam (00–23)') }}</div>
                    <div class="max-h-64 snap-y space-y-1 overflow-y-auto rounded-xl border border-neutral-200 p-1.5 dark:border-neutral-700">
                        @foreach ($this->hours as $hour)
                            <button
                                type="button"
                                wire:click="$set('pickerHour', '{{ $hour }}')"
                                aria-pressed="{{ $pickerHour === $hour ? 'true' : 'false' }}"
                                class="w-full snap-start rounded-lg py-1.5 font-mono text-sm tabular-nums {{ $pickerHour === $hour ? 'bg-zinc-900 font-semibold text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-700 hover:bg-neutral-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                            >
                                {{ $hour }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="text-center text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Menit (00–59)') }}</div>
                    <div class="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-neutral-200 p-1.5 dark:border-neutral-700">
                        @foreach ($this->minutes as $minute)
                            <button
                                type="button"
                                wire:click="$set('pickerMinute', '{{ $minute }}')"
                                aria-pressed="{{ $pickerMinute === $minute ? 'true' : 'false' }}"
                                class="w-full rounded-lg py-1.5 font-mono text-sm tabular-nums {{ $pickerMinute === $minute ? 'bg-zinc-900 font-semibold text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-700 hover:bg-neutral-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                            >
                                {{ $minute }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" wire:click="confirmTimePicker" data-test="confirm-time-picker">
                    {{ __('Pilih') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal konfirmasi hapus -->
    <flux:modal name="session-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Hapus Sesi?') }}</flux:heading>
                <flux:subheading>{{ __('Sesi yang dihapus tidak dapat dikembalikan. Peserta yang terdaftar ikut terlepas dari sesi.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteSession" data-test="confirm-delete-button">
                    {{ __('Ya, Hapus') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal konfirmasi pembatalan (WAJIB sebelum batal) -->
    <flux:modal name="session-cancel" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Batalkan Sesi?') }}</flux:heading>
                <flux:subheading>{{ __('Sesi yang dibatalkan tidak dapat dimulai lagi. Setelah pembatalan, Anda dapat membuat sesi baru.') }}</flux:subheading>
            </div>

            <form wire:submit="cancelSession" class="space-y-4">
                <flux:textarea wire:model="cancel_reason" :label="__('Alasan Pembatalan (opsional)')" placeholder="{{ __('Contoh: lapangan tidak tersedia') }}" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Kembali') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" type="submit" data-test="confirm-cancel-button">
                        {{ __('Ya, Batalkan Sesi') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
