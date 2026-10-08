<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-mist antialiased dark:bg-linear-to-b dark:from-[#1e1e1e] dark:to-[#3a3a3a]">
        <div class="brand-strip h-1 w-full" aria-hidden="true"></div>
        <div class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="logo-chip flex h-11 w-11 items-center justify-center overflow-hidden rounded-lg p-1">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>

                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <div class="flex flex-col gap-6">
                    <div class="dark-surface relative overflow-hidden rounded-xl border bg-white text-stone-800 shadow-xs dark:border-white/10 dark:bg-ash">
                        <span class="dark-card-red-top absolute inset-x-0 top-0 h-1" aria-hidden="true"></span>
                        <div class="px-10 py-8">{{ $slot }}</div>
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
