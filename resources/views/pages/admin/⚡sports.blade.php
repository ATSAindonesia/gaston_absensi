<?php

use App\Actions\Sport\AddCourt;
use App\Actions\Sport\DeleteCourt;
use App\Actions\Sport\DeleteSport;
use App\Actions\Sport\SaveSport;
use App\Exceptions\DomainException;
use App\Models\Court;
use App\Models\Sport;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Kelola Cabor & Lapangan')] class extends Component
{
    public string $sportSearch = '';

    public ?int $sportId = null;

    public string $sportName = '';

    public ?int $deleteSportId = null;

    /** @var array<int, string> */
    public array $courtNames = [];

    public ?int $deleteCourtId = null;

    /**
     * Authorize that only superadmin can access this page.
     */
    public function mount(): void
    {
        Gate::authorize('manage-sports');

        if (! Auth::user()?->isSuperAdmin()) {
            abort(403, 'Akses ditolak. Data cabor dan lapangan hanya dapat dikelola oleh superadmin.');
        }
    }

    /**
     * Open the create form.
     */
    public function openCreateSport(): void
    {
        Gate::authorize('manage-sports');

        $this->reset(['sportId', 'sportName']);
        $this->resetValidation();
    }

    /**
     * Open the edit form for a sport.
     */
    public function openEditSport(int $id): void
    {
        Gate::authorize('manage-sports');

        $sport = Sport::findOrFail($id);

        $this->sportId = $sport->id;
        $this->sportName = $sport->name;
        $this->resetValidation();
    }

    /**
     * Create or update a sport.
     */
    public function saveSport(): void
    {
        Gate::authorize('manage-sports');

        $validated = $this->validate([
            'sportName' => ['required', 'string', 'max:255', Rule::unique('sports', 'name')->ignore($this->sportId)],
        ]);

        if ($this->sportId !== null) {
            app(SaveSport::class)->handle($this->sportId, $validated['sportName']);

            Flux::toast(variant: 'success', text: __('Cabang olahraga berhasil diperbarui.'));
        } else {
            app(SaveSport::class)->handle(null, $validated['sportName']);

            Flux::toast(variant: 'success', text: __('Cabang olahraga berhasil ditambahkan.'));
        }

        $this->reset(['sportId', 'sportName']);
    }

    /**
     * Prepare delete confirmation for a sport.
     */
    public function confirmDeleteSport(int $id): void
    {
        Gate::authorize('manage-sports');

        $this->deleteSportId = $id;
    }

    /**
     * Delete a sport. Blocked while any session uses it or its courts.
     */
    public function deleteSport(): void
    {
        Gate::authorize('manage-sports');

        $sport = Sport::findOrFail($this->deleteSportId);

        try {
            app(DeleteSport::class)->handle($sport);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            $this->reset(['deleteSportId']);

            return;
        }

        $this->reset(['deleteSportId']);

        Flux::toast(variant: 'success', text: __('Cabang olahraga berhasil dihapus.'));
    }

    /**
     * Add a court to a sport.
     */
    public function addCourt(int $sportId): void
    {
        Gate::authorize('manage-sports');

        $validated = $this->validate([
            "courtNames.{$sportId}" => ['required', 'string', 'max:255', Rule::unique('courts', 'name')->where('sport_id', $sportId)],
        ]);

        app(AddCourt::class)->handle($sportId, $validated['courtNames'][$sportId]);

        unset($this->courtNames[$sportId]);

        Flux::toast(variant: 'success', text: __('Lapangan berhasil ditambahkan.'));
    }

    /**
     * Prepare delete confirmation for a court.
     */
    public function confirmDeleteCourt(int $id): void
    {
        Gate::authorize('manage-sports');

        $this->deleteCourtId = $id;
    }

    /**
     * Delete a court. Blocked while any session uses it.
     */
    public function deleteCourt(): void
    {
        Gate::authorize('manage-sports');

        $court = Court::findOrFail($this->deleteCourtId);

        try {
            app(DeleteCourt::class)->handle($court);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
            $this->reset(['deleteCourtId']);

            return;
        }

        $this->reset(['deleteCourtId']);

        Flux::toast(variant: 'success', text: __('Lapangan berhasil dihapus.'));
    }

    /**
     * Get sports with courts and usage counts.
     */
    #[Computed]
    public function sports()
    {
        return Sport::with('courts')
            ->withCount(['courts', 'sessions'])
            ->when($this->sportSearch, fn ($query) => $query->where('name', 'like', '%'.$this->sportSearch.'%'))
            ->orderBy('name')
            ->get();
    }
}; ?>

<section class="w-full">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Kelola Cabor & Lapangan') }}</flux:heading>
                <flux:subheading>{{ __('Halaman khusus superadmin. Data ini menjadi pilihan saat admin membuat sesi.') }}</flux:subheading>
            </div>

            <flux:modal.trigger name="sport-form">
                <flux:button variant="primary" icon="plus" wire:click="openCreateSport" data-test="create-sport-button">
                    {{ __('Tambah Cabor') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
            <span class="font-semibold">{{ __('Pemberitahuan Khusus:') }}</span>
            {{ __('Cabang olahraga atau lapangan yang masih dipakai sesi tidak dapat dihapus.') }}
        </div>

        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.300ms="sportSearch" placeholder="{{ __('Cari cabor...') }}" icon="magnifying-glass" />
        </div>

        <div class="space-y-4">
            @forelse ($this->sports as $sport)
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-zinc-900" wire:key="sport-{{ $sport->id }}">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $sport->name }}</span>
                            <flux:badge color="zinc" size="sm">{{ __(':count lapangan', ['count' => $sport->courts_count]) }}</flux:badge>
                            <flux:badge color="zinc" size="sm">{{ __(':count sesi', ['count' => $sport->sessions_count]) }}</flux:badge>
                        </div>
                        <div class="flex gap-2">
                            <flux:modal.trigger name="sport-form">
                                <flux:button size="sm" variant="ghost" wire:click="openEditSport({{ $sport->id }})">{{ __('Ubah') }}</flux:button>
                            </flux:modal.trigger>
                            <flux:modal.trigger name="sport-delete">
                                <flux:button size="sm" variant="ghost" wire:click="confirmDeleteSport({{ $sport->id }})">{{ __('Hapus') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        @forelse ($sport->courts as $court)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-neutral-200 p-3 text-sm dark:border-neutral-700" wire:key="court-{{ $court->id }}">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $court->name }}</span>
                                <flux:modal.trigger name="court-delete">
                                    <flux:button size="sm" variant="danger" wire:click="confirmDeleteCourt({{ $court->id }})" data-test="delete-court-{{ $court->id }}">{{ __('Hapus') }}</flux:button>
                                </flux:modal.trigger>
                            </div>
                        @empty
                            <p class="py-2 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('Belum ada lapangan untuk cabor ini.') }}</p>
                        @endforelse

                        <form wire:submit="addCourt({{ $sport->id }})" class="flex gap-2 pt-1">
                            <div class="flex-1">
                                <flux:input wire:model="courtNames.{{ $sport->id }}" placeholder="{{ __('Nama lapangan baru...') }}" />
                            </div>
                            <flux:button variant="primary" type="submit" data-test="add-court-{{ $sport->id }}">{{ __('Tambah') }}</flux:button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-neutral-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-neutral-700 dark:bg-zinc-900 dark:text-zinc-400">
                    {{ __('Belum ada cabang olahraga. Tambahkan yang pertama.') }}
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal form create/edit cabor -->
    <flux:modal name="sport-form" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $sportId ? __('Ubah Cabor') : __('Tambah Cabor') }}</flux:heading>
                <flux:subheading>{{ __('Nama cabang olahraga, mis. Badminton.') }}</flux:subheading>
            </div>

            <form wire:submit="saveSport" class="space-y-4">
                <flux:input wire:model="sportName" :label="__('Nama Cabor')" required placeholder="{{ __('Badminton') }}" />

                <div class="flex justify-end gap-2 pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" data-test="save-sport-button">
                        {{ __('Simpan') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Modal konfirmasi hapus cabor -->
    <flux:modal name="sport-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Hapus Cabor?') }}</flux:heading>
                <flux:subheading>{{ __('Seluruh lapangan di dalamnya ikut terhapus. Cabor yang dipakai sesi tidak dapat dihapus.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteSport" data-test="confirm-delete-sport-button">
                    {{ __('Ya, Hapus') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Modal konfirmasi hapus lapangan -->
    <flux:modal name="court-delete" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Hapus Lapangan?') }}</flux:heading>
                <flux:subheading>{{ __('Lapangan yang dipakai sesi tidak dapat dihapus.') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteCourt" data-test="confirm-delete-court-button">
                    {{ __('Ya, Hapus') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
