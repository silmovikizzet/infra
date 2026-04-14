<?php

namespace App\Livewire\Admin;

use App\Models\Credential;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('Credential - Network')]
class CredentialManagement extends Component
{
  use WithPagination;

  public string $search = '';
  public string $filterType = '';

  /** @var array<int,string> */
  public array $typeOptions = [
    'ssh',
    'winbox',
    'api',
    'api-ssl',
    'web',
    'snmp',
    'other',
  ];

  // modal state
  public bool $isEdit = false;
  public ?int $editingId = null;

  // form fields
  public string $name = '';
  public string $username = '';
  public string $password = '';
  public string $password_confirmation = '';
  public string $port = '';
  public string $type = 'ssh';

  protected $queryString = [
    'search' => ['except' => ''],
    'filterType' => ['except' => ''],
  ];

  public function mount(): void
  {
    $this->guardAdmin();
  }

  private function guardAdmin(): void
  {
    if (!auth()->check() || auth()->user()->role !== 'Admin') {
      abort(403, 'Hanya Admin yang boleh akses menu ini.');
    }
  }

  public function updatedSearch(): void
  {
    $this->resetPage();
  }

  public function updatedFilterType(): void
  {
    $this->resetPage();
  }

  private function rulesForStore(): array
  {
    return [
      'name' => ['required', 'string', 'max:100'],
      'username' => ['required', 'string', 'max:100'],
      'password' => ['required', 'string', 'min:1', 'confirmed'],
      'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
      'type' => ['required', Rule::in($this->typeOptions)],
    ];
  }

  private function rulesForUpdate(int $id): array
  {
    return [
      'name' => ['required', 'string', 'max:100'],
      'username' => ['required', 'string', 'max:100'],
      // saat edit password optional
      'password' => ['nullable', 'string', 'min:1', 'confirmed'],
      'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
      'type' => ['required', Rule::in($this->typeOptions)],
    ];
  }

  protected function messages(): array
  {
    return [
      'name.required' => 'Nama credential wajib diisi.',
      'name.max' => 'Nama credential maksimal 100 karakter.',

      'username.required' => 'Username wajib diisi.',
      'username.max' => 'Username maksimal 100 karakter.',

      'password.required' => 'Password wajib diisi.',
      'password.confirmed' => 'Konfirmasi password tidak sama.',

      'port.integer' => 'Port harus berupa angka.',
      'port.min' => 'Port minimal 1.',
      'port.max' => 'Port maksimal 65535.',

      'type.required' => 'Type wajib dipilih.',
      'type.in' => 'Type credential tidak valid.',
    ];
  }

  private function resetForm(): void
  {
    $this->resetValidation();

    $this->isEdit = false;
    $this->editingId = null;

    $this->name = '';
    $this->username = '';
    $this->password = '';
    $this->password_confirmation = '';
    $this->port = '';
    $this->type = 'ssh';
  }

  public function openCreate(): void
  {
    $this->guardAdmin();
    $this->resetForm();
    $this->dispatch('credential-modal:open');
  }

  public function edit(int $id): void
  {
    $this->guardAdmin();
    $this->resetForm();

    $credential = Credential::query()->findOrFail($id);

    $this->isEdit = true;
    $this->editingId = (int) $credential->id;

    $this->name = (string) $credential->name;
    $this->username = (string) $credential->username;
    $this->port = $credential->port !== null ? (string) $credential->port : '';
    $this->type = (string) ($credential->type ?? 'ssh');

    // password sengaja tidak diprefill demi keamanan
    $this->password = '';
    $this->password_confirmation = '';

    $this->dispatch('credential-modal:open');
  }

  public function closeModal(): void
  {
    $this->dispatch('credential-modal:close');
  }

  public function store(): void
  {
    $this->guardAdmin();

    $validated = $this->validate($this->rulesForStore());

    Credential::query()->create([
      'name' => trim($validated['name']),
      'username' => trim($validated['username']),
      'password' => $validated['password'], // auto-encrypt oleh mutator model
      'port' => $validated['port'] === '' || $validated['port'] === null ? null : (int) $validated['port'],
      'type' => $validated['type'],
    ]);

    session()->flash('message', 'Credential berhasil dibuat.');
    $this->dispatch('credential-modal:close');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'Credential berhasil dibuat.']);
    $this->resetForm();
  }

  public function update(): void
  {
    $this->guardAdmin();

    $id = (int) ($this->editingId ?? 0);
    if ($id <= 0) {
      abort(404);
    }

    $validated = $this->validate($this->rulesForUpdate($id));

    $credential = Credential::query()->findOrFail($id);

    $credential->fill([
      'name' => trim($validated['name']),
      'username' => trim($validated['username']),
      'port' => $validated['port'] === '' || $validated['port'] === null ? null : (int) $validated['port'],
      'type' => $validated['type'],
    ]);

    if (trim((string) $this->password) !== '') {
      $credential->password = $this->password; // auto-encrypt oleh mutator model
    }

    $credential->save();

    session()->flash('message', 'Credential berhasil diupdate.');
    $this->dispatch('credential-modal:close');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'Credential berhasil diupdate.']);
    $this->resetForm();
  }

  public function delete(int $id): void
  {
    $this->guardAdmin();

    $credential = Credential::query()->findOrFail($id);

    // optional: kalau ingin cegah hapus credential yg masih dipakai asset
    // if ($credential->assets()->exists()) {
    //     $this->dispatch('toast', ['type' => 'danger', 'message' => 'Credential masih digunakan asset.']);
    //     return;
    // }

    $credential->delete();

    session()->flash('message', 'Credential berhasil dihapus.');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'Credential berhasil dihapus.']);
  }

  public function render()
  {
    $this->guardAdmin();

    $q = Credential::query();

    if ($this->search !== '') {
      $s = trim($this->search);

      $q->where(function ($w) use ($s) {
        $w->where('name', 'like', "%{$s}%")
          ->orWhere('username', 'like', "%{$s}%")
          ->orWhere('type', 'like', "%{$s}%")
          ->orWhere('port', 'like', "%{$s}%");
      });
    }

    if ($this->filterType !== '') {
      $q->where('type', $this->filterType);
    }

    $credentials = $q->orderBy('id', 'desc')->paginate(10);

    return view('livewire.admin.credential-management', [
      'credentials' => $credentials,
    ]);
  }
}
