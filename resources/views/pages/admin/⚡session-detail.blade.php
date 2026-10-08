<?php

use App\Actions\Session\AddSessionParticipants;
use App\Actions\Session\CancelSession;
use App\Actions\Session\FinishSession;
use App\Actions\Session\RemoveSessionParticipant;
use App\Actions\Session\StartSession;
use App\Exceptions\DomainException;
use App\Models\AttendanceSession;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Detail Sesi')] class extends Component {
    use WithPagination;

    public AttendanceSession $session;

    public string $participantSearch = '';

    public string $bulkSearch = '';

    /** @var list<int> */
    public array $selectedUsers = [];

    public ?int $removeTargetId = null;

    public string $cancel_reason = '';

    /**
     * Authorize and load the session.
     */
    public function mount(AttendanceSession|int|string $session): void
    {
        Gate::authorize('manage-sessions');

        $this->session = $session instanceof AttendanceSession
            ? $session
            : AttendanceSession::findOrFail($session);
        $this->session->load(['creator', 'participants.roles']);
    }

    /**
     * Determine if the current viewer may only read this room.
     * The rule itself lives in SessionPolicy; this only exposes it to the view.
     */
    #[Computed]
    public function isReadOnly(): bool
    {
        $user = Auth::user();

        return $user === null || $user->cannot('manage', $this->session);
    }

    /**
     * Deny with a toast when the current user may not manage this room.
     */
    public function authorizeRoomOwnership(): bool
    {
        if (! $this->isReadOnly) {
            return true;
        }

        Flux::toast(variant: 'danger', text: __('Room ini milik admin lain dan hanya dapat dilihat (read-only).'));

        return false;
    }

    /**
     * Refresh the session model state.
     */
    public function refreshSession(): void
    {
        $this->session->refresh();
        $this->session->load(['creator', 'participants.roles']);
    }

    public function updatedParticipantSearch(): void
    {
        $this->resetPage('participantsPage');
    }

    public function updatedBulkSearch(): void
    {
        $this->reset(['selectedUsers']);
    }

    /**
     * Add many users (bulk) to the session.
     */
    public function addBulkParticipants(): void
    {
        Gate::authorize('manage-sessions');

        if (! $this->authorizeRoomOwnership()) {
            return;
        }

        $validated = $this->validate([
            'selectedUsers' => ['required', 'array', 'min:1'],
            'selectedUsers.*' => ['integer', 'exists:users,id'],
        ]);

        try {
            $result = app(AddSessionParticipants::class)->handle($this->session, $validated['selectedUsers']);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->reset(['selectedUsers']);
        $this->refreshSession();

        Flux::toast(
            variant: 'success',
            text: $result['already'] > 0
                ? __(':added pengguna ditambahkan, :skipped sudah terdaftar sebelumnya.', ['added' => count($result['added']), 'skipped' => $result['already']])
                : __(':added pengguna berhasil didaftarkan ke sesi.', ['added' => count($result['added'])])
        );
    }

    /**
     * Prepare remove confirmation for a participant.
     */
    public function confirmRemove(int $userId): void
    {
        Gate::authorize('manage-sessions');

        if (! $this->authorizeRoomOwnership()) {
            return;
        }

        $this->removeTargetId = $userId;
    }

    /**
     * Remove a participant from the session (data user global tidak dihapus).
     */
    public function removeParticipant(): void
    {
        Gate::authorize('manage-sessions');

        if (! $this->authorizeRoomOwnership()) {
            return;
        }

        app(RemoveSessionParticipant::class)->handle($this->session, (int) $this->removeTargetId);
        $this->reset(['removeTargetId']);
        $this->refreshSession();

        Flux::modal('participant-remove')->close();

        Flux::toast(variant: 'success', text: __('Pengguna dikeluarkan dari sesi.'));
    }

    /**
     * Start the session (max 1 active).
     */
    public function startSession(): void
    {
        Gate::authorize('manage-sessions');

        $this->session->refresh();

        if (! $this->authorizeRoomOwnership()) {
            $this->refreshSession();

            return;
        }

        try {
            app(StartSession::class)->handle($this->session);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            $this->refreshSession();

            return;
        }

        Flux::toast(variant: 'success', text: __('Sesi dimulai. Sekarang berstatus Aktif.'));

        $this->refreshSession();
    }

    /**
     * Finish the session.
     */
    public function finishSession(): void
    {
        Gate::authorize('manage-sessions');

        $this->session->refresh();

        if (! $this->authorizeRoomOwnership()) {
            return;
        }

        try {
            app(FinishSession::class)->handle($this->session);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->refreshSession();

        Flux::toast(variant: 'success', text: __('Sesi diselesaikan. Anda dapat membuat sesi baru.'));
    }

    /**
     * Cancel the session (wajib lewat modal konfirmasi).
     */
    public function cancelSession(): void
    {
        Gate::authorize('manage-sessions');

        $validated = $this->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->session->refresh();

        if (! $this->authorizeRoomOwnership()) {
            return;
        }

        try {
            app(CancelSession::class)->handle($this->session, $validated['cancel_reason'] ?? null);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->reset(['cancel_reason']);
        $this->refreshSession();

        Flux::modal('detail-cancel')->close();

        Flux::toast(variant: 'success', text: __('Sesi dibatalkan. Anda dapat membuat sesi baru.'));
    }

    /**
     * Get paginated participants.
     */
    #[Computed]
    public function participants()
    {
        return $this->session->participants()
            ->with('roles')
            ->when($this->participantSearch, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%'.$this->participantSearch.'%')
                        ->orWhere('member_code', 'like', '%'.$this->participantSearch.'%');
                });
            })
            ->orderBy('name')
            ->paginate(10, pageName: 'participantsPage');
    }

    /**
     * Map user_id => scanned_at mentah untuk seluruh sesi (bukan per halaman),
     * agar status Hadir benar walau tabel dipaginasi / dicari.
     */
    #[Computed]
    public function attendedScans()
    {
        return $this->session->attendances()->pluck('scanned_at', 'user_id');
    }

    /**
     * Get users available for bulk registration (excluding current participants).
     */
    #[Computed]
    public function availableUsers()
    {
        $participantIds = $this->session->participants()->pluck('users.id')->all();
        $busyIds = $this->session->busyParticipantIds();

        return User::with('roles')
            ->whereNotIn('id', $participantIds)
            ->whereNotIn('id', $busyIds)
            ->when($this->bulkSearch, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%'.$this->bulkSearch.'%')
                        ->orWhere('member_code', 'like', '%'.$this->bulkSearch.'%');
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4">
        <a href="{{ route('admin.sessions') }}" wire:navigate class="text-sm text-zinc-500 hover:underline dark:text-zinc-400">
            {{ __('← Kembali ke daftar sesi') }}
        </a>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ $session->name }}</flux:heading>
                <flux:subheading>
                    {{ $session->sport?->name ?? '-' }} · {{ $session->court?->name ?? '-' }} ·
                    {{ $session->starts_at->format('d M Y H:i') }} – {{ $session->ends_at->format('H:i') }} ·
                    {{ __('Dibuat oleh') }} {{ $session->creator?->name ?? '-' }}
                </flux:subheading>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <flux:badge color="{{ $session->status->color() }}" size="sm">{{ $session->status->label() }}</flux:badge>
                    @if ($this->isReadOnly)
                        <flux:badge color="zinc" size="sm">{{ __('Read-only · Milik :name', ['name' => $session->creator?->name ?? '-']) }}</flux:badge>
                    @elseif ($session->isOwnedBy(auth()->id()))
                        <flux:badge color="blue" size="sm">{{ __('Room Saya') }}</flux:badge>
                    @endif
                </div>
                @if ($this->isReadOnly)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ __('Room ini milik admin lain. Anda hanya dapat melihat info dan peserta tanpa mengubahnya.') }}
                    </div>
                @endif
            </div>

            @if (! $this->isReadOnly)
            <div class="flex flex-wrap gap-2">
                @if ($session->status === \App\Enums\SessionStatus::Active)
                    <flux:modal.trigger name="session-qr">
                        <flux:button size="sm" variant="filled" icon="qr-code" data-test="detail-qr-preview">{{ __('Preview QR') }}</flux:button>
                    </flux:modal.trigger>
                @endif
                @if ($session->status === \App\Enums\SessionStatus::Soon)
                    <flux:button size="sm" variant="filled" wire:click="startSession" data-test="detail-start">{{ __('Mulai Sesi') }}</flux:button>
                @endif
                @if ($session->status === \App\Enums\SessionStatus::Active)
                    <flux:button size="sm" variant="primary" wire:click="finishSession" data-test="detail-finish">{{ __('Selesaikan') }}</flux:button>
                @endif
                @if (in_array($session->status, [\App\Enums\SessionStatus::Soon, \App\Enums\SessionStatus::Active], true))
                    <flux:modal.trigger name="detail-cancel">
                        <flux:button size="sm" variant="danger" data-test="detail-cancel-open">{{ __('Batalkan') }}</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if (! $this->isReadOnly)
    <div class="rounded-xl border border-silver/60 bg-white p-5 dark:border-white/10 dark:bg-ash">
            <flux:heading size="lg">{{ __('Tambahkan Anggota') }}</flux:heading>
            <flux:subheading>{{ __('Centang beberapa pengguna lalu daftarkan sekaligus. Anggota yang bentrok jam disembunyikan otomatis. Penambahan di tengah sesi aktif diperbolehkan.') }}</flux:subheading>

            <div class="mt-4">
                <flux:input wire:model.live.debounce.300ms="bulkSearch" placeholder="{{ __('Cari nama atau kode...') }}" icon="magnifying-glass" />
            </div>

            <form wire:submit="addBulkParticipants" class="mt-4 space-y-3">
                <div class="max-h-64 space-y-2 overflow-y-auto">
                    @forelse ($this->availableUsers as $available)
                        <label class="flex items-center gap-3 rounded-lg border border-silver/60 p-3 text-sm dark:border-white/10" wire:key="available-{{ $available->id }}">
                            <input type="checkbox" wire:model.live="selectedUsers" value="{{ $available->id }}" class="size-4 rounded" data-test="bulk-{{ $available->id }}" />
                            <span class="flex-1">
                                <span class="block font-mono text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $available->member_code ?? '-' }}</span>
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $available->name }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('Tidak ada pengguna tersedia. Semua sudah terdaftar atau bentrok jam.') }}</p>
                    @endforelse
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Dipilih: :count', ['count' => count($selectedUsers)]) }}</span>
                    <flux:button variant="primary" type="submit" data-test="add-bulk-button">{{ __('Daftarkan Terpilih') }}</flux:button>
                </div>
            </form>
    </div>
    @endif

    <!-- Tabel peserta -->
    <div class="rounded-xl border border-silver/60 bg-white dark:border-white/10 dark:bg-ash">
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">{{ __('Peserta Sesi') }} ({{ $this->participants->total() }})</flux:heading>
                <flux:subheading>{{ __('Kelola, cari, dan keluarkan peserta dari sesi.') }}</flux:subheading>
            </div>
            <div class="w-full max-w-xs">
                <flux:input wire:model.live.debounce.300ms="participantSearch" placeholder="{{ __('Cari peserta...') }}" icon="magnifying-glass" />
            </div>
        </div>

        <div class="overflow-x-auto border-t border-silver/60 dark:border-white/10">
            <table class="min-w-full divide-y divide-neutral-200 dark:divide-white/10 text-sm">
                <thead class="bg-neutral-50 dark:bg-white/5">
                    <tr>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Nama') }}</th>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Kode') }}</th>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Status Hadir') }}</th>
                        @if (! $this->isReadOnly)
                            <th class="px-6 py-3.5 text-right font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Aksi') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/10">
                    @php($attendedScans = $this->attendedScans)
                    @forelse ($this->participants as $p)
                        <tr wire:key="participant-{{ $p->id }}">
                            <td class="px-6 py-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $p->name }}</td>
                            <td class="px-6 py-4 font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $p->member_code ?? '-' }}</td>
                            <td class="px-6 py-4">
                                @php($scannedAt = $attendedScans[$p->id] ?? null)
                                @if ($scannedAt)
                                    <flux:badge color="green" size="sm">{{ __('Hadir') }}</flux:badge>
                                    <span
                                        class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400"
                                        title="{{ \Carbon\Carbon::parse($scannedAt)->format('d M Y H:i:s') }}"
                                    >{{ \Carbon\Carbon::parse($scannedAt)->format('H:i') }}</span>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Tidak Hadir') }}</flux:badge>
                                @endif
                            </td>
                            @if (! $this->isReadOnly)
                                <td class="px-6 py-4 text-right">
                                    <flux:modal.trigger name="participant-remove">
                                        <flux:button size="sm" variant="danger" wire:click="confirmRemove({{ $p->id }})" data-test="remove-{{ $p->id }}">{{ __('Keluarkan') }}</flux:button>
                                    </flux:modal.trigger>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->isReadOnly ? 3 : 4 }}" class="px-6 py-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('Belum ada peserta di sesi ini.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-5">
            {{ $this->participants->links() }}
        </div>
    </div>

    <!-- Modal preview QR absensi (dibungkus iframe, rotasi tiap 10 detik) -->
    <flux:modal name="session-qr" class="max-w-md">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('QR Absensi: :name', ['name' => $session->name]) }}</flux:heading>
                <flux:subheading>{{ __('QR berganti otomatis setiap 10 detik. Hanya peserta terdaftar yang bisa absen.') }}</flux:subheading>
            </div>

            @if ($session->isActive())
                <iframe
                    src="{{ route('admin.sessions.qr', $session) }}"
                    title="{{ __('QR Absensi: :name', ['name' => $session->name]) }}"
                    class="h-[380px] w-full rounded-xl bg-black ring-2 ring-brand/40"
                    loading="lazy"
                ></iframe>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Tutup') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <!-- Modal konfirmasi keluarkan peserta -->
    <flux:modal name="participant-remove" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Keluarkan Peserta?') }}</flux:heading>
                <flux:subheading>{{ __('Peserta hanya dilepas dari sesi ini, data pengguna tidak dihapus.') }}</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="removeParticipant" data-test="confirm-remove-button">{{ __('Ya, Keluarkan') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal konfirmasi pembatalan (WAJIB) -->
    <flux:modal name="detail-cancel" class="md:w-96">
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
                    <flux:button variant="danger" type="submit" data-test="detail-confirm-cancel">{{ __('Ya, Batalkan Sesi') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
