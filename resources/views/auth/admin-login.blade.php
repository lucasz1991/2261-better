<x-guest-layout>
    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-semibold text-slate-900">Administrator Login</h1>
            <p class="mt-1 text-sm text-slate-600">Anmeldung fuer den geschuetzten Bereich unter <span class="font-medium">/administrator</span>.</p>

            <x-validation-errors class="mt-4 mb-4" />

            @if (session('status'))
                <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="admin_login" value="1">

                <div>
                    <x-label for="email" value="E-Mail" />
                    <x-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                </div>

                <div>
                    <x-label for="password" value="Passwort" />
                    <x-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember" class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <x-checkbox id="remember" name="remember" />
                        Angemeldet bleiben
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm text-slate-600 underline hover:text-slate-900">
                        Passwort vergessen?
                    </a>
                </div>

                <x-button class="w-full justify-center">
                    In Adminbereich einloggen
                </x-button>
            </form>
        </div>
    </div>
</x-guest-layout>
