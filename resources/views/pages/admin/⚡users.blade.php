<?php

use App\Actions\User\RegisterMember;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Informasi Pengguna')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $showRegisterModal = false;

    /**
     * Authorize user access.
     */
    public function mount(): void
    {
        Gate::authorize('manage-users');
    }

    /**
     * Register a new user as anggota.
     */
    public function registerUser(): void
    {
        Gate::authorize('create-users');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $user = app(RegisterMember::class)->handle($validated);

        $this->reset(['name', 'email', 'password', 'password_confirmation', 'showRegisterModal']);

        Flux::modal('register-user-modal')->close();

        Flux::toast(variant: 'success', text: __('Pengguna berhasil didaftarkan dengan kode :code.', ['code' => $user->member_code]));
    }

    /**
     * Get paginated users with roles.
     */
    #[Computed]
    public function users()
    {
        return User::with('roles')
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('member_code', 'like', '%'.$this->search.'%');
                });
            })
            ->latest()
            ->paginate(10);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl" level="1">{{ __('Informasi Pengguna') }}</flux:heading>
                <flux:subheading>{{ __('Daftar pengguna dan registrasi pengguna baru.') }}</flux:subheading>
            </div>

            <flux:modal.trigger name="register-user-modal">
                <flux:button variant="primary" icon="user-plus">
                    {{ __('Daftarkan Pengguna') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <div class="flex items-center gap-4">
            <div class="w-full max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari nama, email, atau kode...') }}" icon="magnifying-glass" />
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-neutral-200 bg-white dark:border-white/10 dark:bg-ash">
            <table class="min-w-full divide-y divide-neutral-200 dark:divide-white/10 text-sm">
                <thead class="bg-neutral-50 dark:bg-white/5">
                    <tr>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Kode') }}</th>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Nama') }}</th>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Email') }}</th>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Role') }}</th>
                        <th class="px-6 py-3.5 text-left font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Terdaftar') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-white/10">
                    @forelse ($this->users as $u)
                        <tr class="hover:bg-neutral-50/50 dark:hover:bg-white/10">
                            <td class="px-6 py-4 font-mono text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ $u->member_code ?? '-' }}</td>
                            <td class="px-6 py-4 font-medium text-zinc-900 dark:text-zinc-100">{{ $u->name }}</td>
                            <td class="px-6 py-4 text-zinc-600 dark:text-zinc-400">{{ $u->email }}</td>
                            <td class="px-6 py-4">
                                @if ($u->isSuperAdmin())
                                    <flux:badge color="purple" size="sm">{{ __('Super Admin') }}</flux:badge>
                                @elseif ($u->isAdmin())
                                    <flux:badge color="blue" size="sm">{{ __('Admin') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Anggota') }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-zinc-500 dark:text-zinc-400">{{ $u->created_at?->format('d M Y H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('Tidak ada pengguna ditemukan.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-2">
            {{ $this->users->links() }}
        </div>

        <!-- Modal Pendaftaran Pengguna oleh Admin -->
        <flux:modal name="register-user-modal" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Daftarkan Pengguna Baru') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Pengguna baru yang didaftarkan otomatis mendapatkan role "Anggota".') }}
                    </flux:subheading>
                </div>

                <form wire:submit="registerUser" class="space-y-4">
                    <flux:input wire:model="name" :label="__('Nama')" required autocomplete="name" placeholder="{{ __('Nama lengkap') }}" />

                    <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" placeholder="user@example.com" />

                    <flux:input wire:model="password" :label="__('Password')" type="password" required viewable />

                    <flux:input wire:model="password_confirmation" :label="__('Konfirmasi Password')" type="password" required viewable />

                    <div class="rounded-lg bg-blue-50 p-3 text-xs text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">
                        {{ __('Catatan: Pengguna yang didaftarkan admin akan otomatis memiliki role "Anggota" (bukan admin). Role admin hanya dapat diberikan oleh Super Admin melalui pengaturan khusus.') }}
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Batal') }}</flux:button>
                        </flux:modal.close>

                        <flux:button variant="primary" type="submit" data-test="admin-register-button">
                            {{ __('Simpan & Daftarkan') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    </div>
</div>
