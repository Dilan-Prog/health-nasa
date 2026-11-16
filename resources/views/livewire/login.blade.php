<main class="lg">
    <form class="lg-card" wire:submit="entrar" novalidate>
        <span class="mono lg-et">HEALTH MONITOR</span>
        <h1 class="lg-h1">Sign in</h1>
        <p class="lg-txt">Sign in with your mission account. This tool provides guidance only; it does not diagnose.</p>

        @if ($error)
            <p class="lg-error" role="alert"><span class="icono" aria-hidden="true">error</span>{{ $error }}</p>
        @endif

        <label class="lg-campo">
            <span class="lg-lbl">Email</span>
            <input class="lg-input" type="email" name="email" wire:model="email" autocomplete="username" inputmode="email" required autofocus>
        </label>
        <label class="lg-campo">
            <span class="lg-lbl">Password</span>
            <input class="lg-input" type="password" name="password" wire:model="password" autocomplete="current-password" required>
        </label>
        <label class="lg-check">
            <input type="checkbox" wire:model="recordar"> <span>Keep me signed in</span>
        </label>

        <button type="submit" class="lg-btn" wire:loading.attr="disabled">
            <span class="icono" aria-hidden="true">login</span>Sign in
        </button>
        <a class="lg-enlace" href="{{ route('aviso-legal') }}">Legal notice</a>
    </form>
</main>
