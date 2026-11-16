<main class="lg">
    <form class="lg-card" wire:submit="entrar" novalidate>
        <span class="mono lg-et">MONITOR DE SALUD</span>
        <h1 class="lg-h1">Iniciar sesión</h1>
        <p class="lg-txt">Accede con tu cuenta de la misión. Esta herramienta orienta, no diagnostica.</p>

        @if ($error)
            <p class="lg-error" role="alert"><span class="icono" aria-hidden="true">error</span>{{ $error }}</p>
        @endif

        <label class="lg-campo">
            <span class="lg-lbl">Correo electrónico</span>
            <input class="lg-input" type="email" name="email" wire:model="email" autocomplete="username" inputmode="email" required autofocus>
        </label>
        <label class="lg-campo">
            <span class="lg-lbl">Contraseña</span>
            <input class="lg-input" type="password" name="password" wire:model="password" autocomplete="current-password" required>
        </label>
        <label class="lg-check">
            <input type="checkbox" wire:model="recordar"> <span>Mantener la sesión iniciada</span>
        </label>

        <button type="submit" class="lg-btn" wire:loading.attr="disabled">
            <span class="icono" aria-hidden="true">login</span>Entrar
        </button>
        <a class="lg-enlace" href="{{ route('aviso-legal') }}">Aviso legal</a>
    </form>
</main>
