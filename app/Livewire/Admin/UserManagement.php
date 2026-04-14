<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\SiteUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('VLAN - Network')]
class UserManagement extends Component
{
  use WithPagination, WithFileUploads;

  public string $search = '';
  public string $filterRole = '';
  public string $filterSite = '';

  public array $siteOptions = [];
  public array $roleOptions = ['Admin', 'User'];

  // modal state
  public bool $isEdit = false;
  public ?int $editingId = null;

  // form fields
  public string $name = '';
  public string $email = '';
  public string $location = '';
  public string $role = 'User';

  public string $password = '';
  public string $password_confirmation = '';

  /** @var array<int,string> */
  public array $sites = []; // list site string

  // upload image (optional)
  public $profile_image = null; // Livewire tmp file
  public ?string $existing_profile_image = null;

  protected $queryString = [
    'search' => ['except' => ''],
    'filterRole' => ['except' => ''],
    'filterSite' => ['except' => ''],
  ];

  public function mount(): void
  {
    $this->guardAdmin();
    $this->loadSiteOptions();
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
  public function updatedFilterRole(): void
  {
    $this->resetPage();
  }
  public function updatedFilterSite(): void
  {
    $this->resetPage();
  }

  private function loadSiteOptions(): void
  {
    // ambil seluruh site yang ada di SiteUser (unik)
    $raw = SiteUser::query()->pluck('site')->toArray();

    $clean = array_values(array_unique(array_filter(array_map(function ($s) {
      $s = trim((string) $s);
      $s = preg_replace('/\s+/', ' ', $s);
      return $s;
    }, $raw))));

    sort($clean);
    $this->siteOptions = $clean;
  }

  private function rulesForStore(): array
  {
    return [
      'name' => ['required', 'string', 'max:100'],
      'email' => ['required', 'email:rfc,dns', 'max:150', 'unique:users,email'],
      'location' => ['nullable', 'string', 'max:120'],
      'role' => ['required', Rule::in($this->roleOptions)],

      'password' => ['required', 'string', 'min:8', 'confirmed'],

      'sites' => ['array'],
      'sites.*' => ['string', 'max:50'],

      'profile_image' => ['nullable', 'image', 'max:2048'], // 2MB
    ];
  }

  private function rulesForUpdate(int $id): array
  {
    return [
      'name' => ['required', 'string', 'max:100'],
      'email' => ['required', 'email:rfc,dns', 'max:150', Rule::unique('users', 'email')->ignore($id)],
      'location' => ['nullable', 'string', 'max:120'],
      'role' => ['required', Rule::in($this->roleOptions)],

      // saat edit: password optional
      'password' => ['nullable', 'string', 'min:8', 'confirmed'],

      'sites' => ['array'],
      'sites.*' => ['string', 'max:50'],

      'profile_image' => ['nullable', 'image', 'max:2048'],
    ];
  }

  protected function messages(): array
  {
    return [
      'name.required' => 'Nama wajib diisi.',
      'email.required' => 'Email wajib diisi.',
      'email.email' => 'Format email tidak valid.',
      'email.unique' => 'Email sudah dipakai user lain.',
      'role.required' => 'Role wajib dipilih.',
      'role.in' => 'Role tidak valid.',
      'password.required' => 'Password wajib diisi.',
      'password.min' => 'Password minimal 8 karakter.',
      'password.confirmed' => 'Konfirmasi password tidak sama.',
      'profile_image.image' => 'File foto harus berupa gambar.',
      'profile_image.max' => 'Ukuran foto maksimal 2MB.',
    ];
  }

  private function resetForm(): void
  {
    $this->resetValidation();
    $this->isEdit = false;
    $this->editingId = null;

    $this->name = '';
    $this->email = '';
    $this->location = '';
    $this->role = 'User';

    $this->password = '';
    $this->password_confirmation = '';

    $this->sites = [];

    $this->profile_image = null;
    $this->existing_profile_image = null;
  }

  public function openCreate(): void
  {
    $this->guardAdmin();
    $this->resetForm();
    $this->dispatch('user-modal:open');
  }

  public function edit(int $id): void
  {
    $this->guardAdmin();
    $this->resetForm();

    $u = User::query()->with('sites')->findOrFail($id);

    $this->isEdit = true;
    $this->editingId = (int) $u->id;

    $this->name = (string) $u->name;
    $this->email = (string) $u->email;
    $this->location = (string) ($u->location ?? '');
    $this->role = (string) ($u->role ?? 'User');

    $this->existing_profile_image = $u->profile_image ? (string) $u->profile_image : null;

    $this->sites = $u->sites()->pluck('site')->map(fn($s) => (string) $s)->toArray();

    $this->dispatch('user-modal:open');
  }

  public function closeModal(): void
  {
    $this->dispatch('user-modal:close');
  }

  public function store(): void
  {
    $this->guardAdmin();

    $this->validate($this->rulesForStore());

    $path = null;
    if ($this->profile_image) {
      $path = $this->profile_image->store('profile_images', 'public');
    }

    $u = User::query()->create([
      'name' => $this->name,
      'email' => strtolower(trim($this->email)),
      'role' => $this->role,
      'password' => Hash::make($this->password),
      'profile_image' => $path,
    ]);

    // sync sites
    SiteUser::query()->where('user_id', $u->id)->delete();
    foreach ($this->sites as $site) {
      $site = trim((string) $site);
      if ($site === '')
        continue;
      SiteUser::query()->create([
        'user_id' => $u->id,
        'site' => $site,
      ]);
    }

    session()->flash('message', 'User berhasil dibuat.');
    $this->dispatch('user-modal:close');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'User berhasil dibuat.']);
    $this->resetForm();
    $this->loadSiteOptions();
  }

  public function update(): void
  {
    $this->guardAdmin();

    $id = (int) ($this->editingId ?? 0);
    if ($id <= 0)
      abort(404);

    $this->validate($this->rulesForUpdate($id));

    $u = User::query()->findOrFail($id);

    $path = $u->profile_image;
    if ($this->profile_image) {
      $path = $this->profile_image->store('profile_images', 'public');
    }

    $u->fill([
      'name' => $this->name,
      'email' => strtolower(trim($this->email)),
      'role' => $this->role,
      'profile_image' => $path,
    ]);

    if (trim($this->password) !== '') {
      $u->password = Hash::make($this->password);
    }

    $u->save();

    // sync sites
    SiteUser::query()->where('user_id', $u->id)->delete();
    foreach ($this->sites as $site) {
      $site = trim((string) $site);
      if ($site === '')
        continue;
      SiteUser::query()->create([
        'user_id' => $u->id,
        'site' => $site,
      ]);
    }

    session()->flash('message', 'User berhasil diupdate.');
    $this->dispatch('user-modal:close');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'User berhasil diupdate.']);
    $this->resetForm();
    $this->loadSiteOptions();
  }

  public function delete(int $id): void
  {
    $this->guardAdmin();

    // optional: cegah hapus diri sendiri
    if (auth()->id() === $id) {
      $this->dispatch('toast', message: 'Tidak bisa menghapus akun sendiri.', variant: 'danger');
      return;
    }

    SiteUser::query()->where('user_id', $id)->delete();
    User::query()->whereKey($id)->delete();

    session()->flash('message', 'User berhasil dihapus.');
    $this->loadSiteOptions();
  }

  public function render()
  {
    $this->guardAdmin();

    $q = User::query()->with('sites');

    if ($this->search !== '') {
      $s = trim($this->search);
      $q->where(function ($w) use ($s) {
        $w->where('name', 'like', "%{$s}%")
          ->orWhere('email', 'like', "%{$s}%")
          ->orWhere('location', 'like', "%{$s}%");
      });
    }

    if ($this->filterRole !== '') {
      $q->where('role', $this->filterRole);
    }

    if ($this->filterSite !== '') {
      $site = $this->filterSite;
      $q->whereHas('sites', fn($ss) => $ss->where('site', $site));
    }

    $users = $q->orderBy('id', 'desc')->paginate(10);

    return view('livewire.admin.user-management', [
      'users' => $users,
    ]);
  }
}
