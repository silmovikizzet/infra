<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.blankLayoutLivewire')]
#[Title('Login')]
class Login extends Component
{
  public string $email = '';

  public string $password = '';

  public bool $remember_me = false;

  protected function rules(): array
  {
    return [
      'email' => ['required', 'email'],
      'password' => ['required', 'string'],
    ];
  }

  public function mount(): void
  {
    if (Auth::check()) {
      // Jangan gunakan navigate:true karena layout login
      // dan dashboard berbeda.
      $this->redirectRoute('dashboard');

      return;
    }

    $this->email = 'admin@mayapadahospital.com';
    $this->password = '';
  }

  public function login()
  {
    $this->validate();

    $authenticated = Auth::attempt(
      [
        'email' => trim($this->email),
        'password' => $this->password,
      ],
      $this->remember_me
    );

    if (!$authenticated) {
      $this->addError('email', trans('auth.failed'));

      return null;
    }

    request()->session()->regenerate();

    /*
     * Laravel redirect biasa akan melakukan perpindahan halaman penuh.
     * Ini lebih aman setelah login karena layout login dan dashboard berbeda.
     */
    return redirect()->intended(route('dashboard'));
  }

  public function render()
  {
    return view('livewire.auth.login');
  }
}
