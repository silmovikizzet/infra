<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

#[Layout('layouts.blankLayoutLivewire')]
#[Title('Login Basic - Pages')]
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
            $this->redirect('/dashboard', navigate: true);
            return;
        }

        $this->fill([
            'email' => 'admin@mayapadahospital.com',
            'password' => '',
        ]);
    }

    public function login(): void
    {
        $this->validate();

        if (
            !Auth::attempt(
                ['email' => $this->email, 'password' => $this->password],
                $this->remember_me
            )
        ) {
            $this->addError('email', trans('auth.failed'));
            return;
        }

        request()->session()->regenerate();

        $this->redirectIntended('/dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
