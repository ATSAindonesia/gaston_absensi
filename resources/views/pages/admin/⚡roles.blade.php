<?php

use App\Actions\Role\UpdateUserRole;
use App\Exceptions\DomainException;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Pengaturan Role')] class extends Component
{
    use WithPagination;

    public string $search = '';

    /**
     * Authorize that only superadmin can access this special settings page.
     */
    public function mount(): void
    {
        Gate::authorize('manage-roles');

        if (! Auth::user()?->isSuperAdmin()) {
            abort(403, 'Akses ditolak. Pengaturan role hanya dapat diakses oleh admin utama / superadmin.');
        }
    }

    /**
     * Update a user's role. Only superadmin can assign role 'admin'.
     */
    public function updateUserRole(int $userId, string $newRole): void
    {
        Gate::authorize('manage-roles');

        if (! Auth::user()?->isSuperAdmin()) {
            abort(403, 'Akses ditolak. Role admin hanya dapat diberikan oleh admin utama/superadmin.');
        }

        $user = User::findOrFail($userId);

        try {
            app(UpdateUserRole::class)->handle(Auth::user(), $user, $newRole);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(
            variant: 'success',
            text: __('Role untuk :name berhasil diubah menjadi :role.', [
                'name' => $user->name,
                'role' => $user->primaryRoleLabel(),
            ])
        );
    }

    /**
     * Get paginated users for role management.
     */
    #[Computed]
    public function users()
    {
        return User::with('roles')
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(10);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Pengaturan Role') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Pengaturan Role')"
        :subheading="__('Pengaturan khusus admin utama/superadmin untuk memberikan role admin dan mengelola role pengguna.')"
    >
        <div class="my-6 w-full space-y-6">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                <span class="font-semibold">{{ __('Pemberitahuan Khusus:') }}</span>
                {{ __('Halaman pengaturan ini hanya dapat diakses oleh Admin Utama / Superadmin. Role "admin" hanya dapat diberikan melalui menu pengaturan ini.') }}
            </div>

            <div class="w-full">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari nama atau email pengguna...') }}" icon="magnifying-glass" />
            </div>

            <div class="space-y-3">
                @forelse ($this->users as $u)
                    <div class="flex flex-col gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $u->name }}</span>
                                @if ($u->id === auth()->id())
                                    <flux:badge color="zinc" size="sm">{{ __('Anda') }}</flux:badge>
                                @endif
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $u->email }}</div>
                            <div class="pt-1">
                                @if ($u->isSuperAdmin())
                                    <flux:badge color="purple" size="sm">{{ __('Super Admin') }}</flux:badge>
                                @elseif ($u->isAdmin())
                                    <flux:badge color="blue" size="sm">{{ __('Admin') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Anggota') }}</flux:badge>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Ubah Role:') }}</span>

                            @if ($u->isAnggota())
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="updateUserRole({{ $u->id }}, 'admin')"
                                    data-test="grant-admin-{{ $u->id }}"
                                >
                                    {{ __('Jadikan Admin') }}
                                </flux:button>
                            @elseif ($u->isAdmin())
                                <flux:button
                                    size="sm"
                                    variant="filled"
                                    wire:click="updateUserRole({{ $u->id }}, 'anggota')"
                                    data-test="demote-anggota-{{ $u->id }}"
                                >
                                    {{ __('Jadikan Anggota') }}
                                </flux:button>
                            @else
                                <span class="text-xs font-medium text-purple-600 dark:text-purple-400">{{ __('Super Admin Tetap') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Tidak ada pengguna ditemukan.') }}
                    </div>
                @endforelse
            </div>

            <div class="pt-2">
                {{ $this->users->links() }}
            </div>
        </div>
    </x-pages::settings.layout>
</section>
