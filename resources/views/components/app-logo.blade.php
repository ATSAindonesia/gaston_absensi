@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo">
            <span class="logo-chip flex h-9 w-9 items-center justify-center overflow-hidden rounded-md p-1">
                <img src="/logo_gaston.jpeg" alt="Gaston" class="h-full w-full rounded-sm object-contain" />
            </span>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'Laravel')" {{ $attributes }}>
        <x-slot name="logo">
            <span class="logo-chip flex h-9 w-9 items-center justify-center overflow-hidden rounded-md p-1">
                <img src="/logo_gaston.jpeg" alt="Gaston" class="h-full w-full rounded-sm object-contain" />
            </span>
        </x-slot>
    </flux:brand>
@endif
