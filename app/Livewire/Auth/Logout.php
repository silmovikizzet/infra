<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Logout extends Component
{
  public function logout(): void
  {
    Auth::logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    $this->redirect(route('login'), navigate: true);
  }

  public function render()
  {
    // komponen ini cuma "button/link", gak perlu halaman sendiri
    return view('livewire.auth.logout');
  }
}
