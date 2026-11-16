<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Inicio de sesión (correo + contraseña). El error es genérico: no revela si el correo existe. */
#[Layout('layouts.login')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $recordar = false;
    public ?string $error = null;

    public function mount()
    {
        if (Auth::check()) {
            return redirect('/');
        }
    }

    public function entrar()
    {
        $this->error = null;
        $this->email = trim($this->email);

        if ($this->email === '' || $this->password === '') {
            $this->error = 'Escribe tu correo y tu contraseña.';

            return null;
        }

        $clave = Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $seg = RateLimiter::availableIn($clave);
            $this->error = "Demasiados intentos. Vuelve a probar en {$seg} segundos.";

            return null;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->recordar)) {
            RateLimiter::hit($clave, 60);
            $this->password = '';
            $this->error = 'Correo o contraseña incorrectos.';

            return null;
        }

        RateLimiter::clear($clave);
        session()->regenerate();

        return redirect()->intended('/');
    }

    public function render()
    {
        return view('livewire.login');
    }
}
