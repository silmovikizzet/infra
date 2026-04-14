<?php

namespace App\Livewire\Device;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

use App\Models\AssetSwitch;
use App\Models\Credential;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('Switch')]
class NetworkSwitch extends Component
{
  use WithPagination;

  // Bootstrap pagination (karena UI kamu bootstrap)
  protected string $paginationTheme = 'bootstrap';

  // Pagination config
  public int $perPage = 50;

  public string $id = '';

  // form fields
  public ?string $hostname = '';
  public ?string $type = '';
  public ?string $group = '';
  public ?string $ip_address = '';
  public ?string $mac_address = '';
  public ?string $serial_number = '';
  public ?string $end_of_support = null;   // date
  public ?string $warranty = null;         // date
  public ?string $firmware_version = '';
  public ?string $location = null;         // site/location
  public ?string $floor = '';
  public ?string $tower = '';
  public $credential_id = null;            // nullable
  public ?string $remark = '';

  public bool $isEdit = false;

  // list filters
  public string $search = '';
  public ?string $filterSite = null;
  public ?string $filterGroup = null;
  // ========== SORTING ==========
  public string $sortField = 'id';
  public string $sortDirection = 'desc'; // asc|desc

  /** kolom yang diizinkan untuk sort (security) */
  protected array $sortable = [
    'location',
    'hostname',
    'ip_address',
    'mac_address',
    'serial_number',
    'group',
    'type',
    'floor',
    'tower',
    'warranty',
    'firmware_version',
    'id',
    'created_at',
    'updated_at',
  ];
  public array $groupOptions = [
    'access',
    'distri',
    'farm',
    'core',
  ];
  // options
  public array $siteOptions = [];
  public array $credentialOptions = [];

  protected $rules = [
    'location' => 'required|string|max:100',

    'hostname' => 'required|string|max:100',
    'type' => 'nullable|string|max:50',
    'group' => 'nullable|string|max:50',

    'ip_address' => 'nullable|ip',
    'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/'],
    'serial_number' => 'nullable|string|max:100',

    'end_of_support' => 'nullable|date',
    'warranty' => 'nullable|string',

    'firmware_version' => 'nullable|string|max:100',
    'floor' => 'nullable|string|max:50',
    'tower' => 'nullable|string|max:50',

    'credential_id' => 'nullable|integer|exists:credentials,id',

    'remark' => 'nullable|string|max:255',
  ];

  protected array $messages = [
    'location.required' => 'Site/Location wajib dipilih.',
    'location.string' => 'Site/Location tidak valid.',
    'location.max' => 'Site/Location maksimal :max karakter.',

    'hostname.required' => 'Hostname wajib diisi.',
    'hostname.string' => 'Hostname harus berupa teks.',
    'hostname.max' => 'Hostname maksimal :max karakter.',

    'type.max' => 'Type maksimal :max karakter.',
    'group.max' => 'Group maksimal :max karakter.',

    'ip_address.ip' => 'Format IP Address tidak valid. Contoh: 10.1.1.10',
    'mac_address.regex' => 'Format MAC Address tidak valid. Contoh: AA:BB:CC:DD:EE:FF',

    'serial_number.max' => 'Serial Number maksimal :max karakter.',

    'end_of_support.date' => 'End of Support harus format tanggal yang valid.',

    'firmware_version.max' => 'Firmware Version maksimal :max karakter.',
    'floor.max' => 'Floor maksimal :max karakter.',
    'tower.max' => 'Tower maksimal :max karakter.',

    'credential_id.integer' => 'Credential tidak valid.',
    'credential_id.exists' => 'Credential tidak ditemukan.',

    'remark.max' => 'Remark maksimal :max karakter.',
  ];

  public function mount(): void
  {
    if (!auth()->check()) {
      $this->redirect(route('login'), navigate: true);
      return;
    }

    $this->filterSite = request()->query('site', null);
    $this->filterSite = is_string($this->filterSite) ? trim(preg_replace('/\s+/', ' ', $this->filterSite)) : null;
    $this->filterSite = ($this->filterSite === '') ? null : $this->filterSite;

    $this->search = (string) request()->query('q', '');
    $this->search = trim($this->search);

    $this->filterGroup = request()->query('group', null);
    $this->filterGroup = is_string($this->filterGroup) ? trim($this->filterGroup) : null;
    $this->filterGroup = $this->filterGroup === '' ? null : $this->filterGroup;

    if ($this->filterGroup !== null && !in_array($this->filterGroup, $this->groupOptions, true)) {
      $this->filterGroup = null;
    }
    $this->loadSiteOptions();

    // form jangan ikut filter
    $this->location = null;

    $this->loadCredentialOptions();

    if ($this->filterSite !== null && !in_array($this->filterSite, $this->siteOptions, true)) {
      $this->filterSite = null;
    }
  }
  public function sortBy(string $field): void
  {
    if (!in_array($field, $this->sortable, true)) {
      return;
    }

    if ($this->sortField === $field) {
      $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      $this->sortField = $field;
      $this->sortDirection = 'asc';
    }

    $this->resetPage();
  }

  public function resetSort(): void
  {
    $this->sortField = 'id';
    $this->sortDirection = 'desc';
    $this->resetPage();
  }
  private function loadSiteOptions(): void
  {
    $user = auth()->user();

    $rows = $user
      ? $user->sites()->select(['id', 'site'])->orderBy('id', 'asc')->get()
      : collect();

    $seen = [];
    $out = [];

    foreach ($rows as $row) {
      $s = trim((string) ($row->site ?? ''));
      $s = preg_replace('/\s+/', ' ', $s);
      if ($s === '')
        continue;

      if (isset($seen[$s]))
        continue;
      $seen[$s] = true;

      $out[] = $s;
    }

    $this->siteOptions = $out;
  }

  private function loadCredentialOptions(): void
  {
    $rows = Credential::query()
      ->orderBy('id', 'desc')
      ->get(['id', 'name', 'username', 'port']);

    $this->credentialOptions = $rows->map(fn($r) => [
      'id' => (string) $r->id,
      'label' => trim(($r->name ?? ('Credential #' . $r->id)) . ' — ' . ($r->username ?? '-') . ' : ' . ($r->port ?? '')),
    ])->toArray();

    if ($this->credential_id !== '' && $this->credential_id !== null) {
      $ok = collect($this->credentialOptions)->contains('id', (string) $this->credential_id);
      if (!$ok)
        $this->credential_id = '';
    }
  }

  private function baseQuery()
  {
    $query = AssetSwitch::query()->with('credential');

    $user = auth()->user();
    $userSites = $user ? $user->sites()->pluck('site')->toArray() : [];

    if (empty($userSites)) {
      // biar paginator kosong tapi aman
      $query->whereRaw('1=0');
      return $query;
    }

    $query->whereIn('location', $userSites);

    if (!empty($this->filterSite)) {
      $query->where('location', $this->filterSite);
    }
    if (!empty($this->filterGroup)) {
      $query->where('group', $this->filterGroup);
    }
    $term = trim((string) $this->search);
    if ($term !== '') {
      $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

      $query->where(function ($q) use ($like) {
        $q->orWhere('hostname', 'like', $like)
          ->orWhere('ip_address', 'like', $like)
          ->orWhere('mac_address', 'like', $like)
          ->orWhere('serial_number', 'like', $like)
          ->orWhere('type', 'like', $like)
          ->orWhere('group', 'like', $like)
          ->orWhere('location', 'like', $like)
          ->orWhere('floor', 'like', $like)
          ->orWhere('tower', 'like', $like)
          ->orWhere('firmware_version', 'like', $like)
          ->orWhere('remark', 'like', $like);
      });
    }

    return $query;
  }

  public function updatedFilterSite($val): void
  {
    $val = is_string($val) ? trim(preg_replace('/\s+/', ' ', $val)) : null;
    $val = $val === '' ? null : $val;

    $this->filterSite = ($val !== null && in_array($val, $this->siteOptions, true)) ? $val : null;

    // penting: kalau filter berubah, balik ke page 1
    $this->resetPage();
  }
  public function updatedFilterGroup($val): void
  {
    $val = is_string($val) ? trim($val) : null;
    $val = $val === '' ? null : $val;

    $this->filterGroup = ($val !== null && in_array($val, $this->groupOptions, true)) ? $val : null;

    $this->resetPage();
  }
  public function updatedSearch($val): void
  {
    $this->search = trim((string) $val);
    $this->resetPage();
  }

  public function updatedMacAddress($val): void
  {
    $this->mac_address = $this->formatMac($val);
  }

  public function resetForm(): void
  {
    $this->id = '';
    $this->isEdit = false;

    $this->hostname = '';
    $this->type = '';
    $this->group = '';
    $this->ip_address = '';
    $this->mac_address = '';
    $this->serial_number = '';
    $this->end_of_support = null;
    $this->warranty = null;
    $this->firmware_version = '';
    $this->floor = '';
    $this->tower = '';
    $this->credential_id = null;
    $this->remark = '';

    $this->location = null;

    $this->loadCredentialOptions();
  }

  public function openCreate(): void
  {
    $this->resetErrorBag();
    $this->resetValidation();
    $this->resetForm();

    $this->dispatch('asset-modal:open');
  }

  public function store(): void
  {
    $this->normalizeDates();
    $this->validate();

    AssetSwitch::create([
      'hostname' => $this->hostname,
      'type' => $this->type,
      'group' => $this->group,
      'ip_address' => $this->ip_address,
      'mac_address' => $this->mac_address,
      'serial_number' => $this->serial_number,
      'end_of_support' => $this->end_of_support,
      'warranty' => $this->warranty,
      'firmware_version' => $this->firmware_version,
      'location' => $this->location,
      'floor' => $this->floor,
      'tower' => $this->tower,
      'credential_id' => $this->credential_id ?: null,
      'remark' => $this->remark,
    ]);

    $this->dispatch('toast', ['type' => 'success', 'message' => 'Switch berhasil dibuat.']);
    $this->dispatch('asset-modal:close');

    $this->resetForm();
    $this->resetPage();
  }

  public function edit($id): void
  {
    $this->resetErrorBag();
    $this->resetValidation();

    $asset = AssetSwitch::with('credential')->findOrFail($id);

    $this->id = (string) $asset->id;
    $this->isEdit = true;

    $this->hostname = (string) ($asset->hostname ?? '');
    $this->type = (string) ($asset->type ?? '');
    $this->group = (string) ($asset->group ?? '');
    $this->ip_address = (string) ($asset->ip_address ?? '');
    $this->mac_address = $this->formatMac($asset->mac_address);
    $this->serial_number = (string) ($asset->serial_number ?? '');

    $this->end_of_support = $asset->end_of_support ? (string) $asset->end_of_support : null;
    $this->warranty = $asset->warranty ? (string) $asset->warranty : null;

    $this->firmware_version = (string) ($asset->firmware_version ?? '');
    $this->location = is_string($asset->location) ? trim(preg_replace('/\s+/', ' ', $asset->location)) : null;
    $this->floor = (string) ($asset->floor ?? '');
    $this->tower = (string) ($asset->tower ?? '');

    $this->credential_id = $asset->credential_id ? (string) $asset->credential_id : '';
    $this->remark = (string) ($asset->remark ?? '');

    $this->loadCredentialOptions();

    $this->dispatch('asset-modal:open');
  }

  public function update(): void
  {
    if (!$this->id) {
      $this->dispatch('toast', ['type' => 'error', 'message' => 'Tidak ada Switch yang dipilih untuk update.']);
      return;
    }
    $this->normalizeDates();
    $this->validate();

    $asset = AssetSwitch::findOrFail($this->id);
    $asset->update([
      'hostname' => $this->hostname,
      'type' => $this->type,
      'group' => $this->group,
      'ip_address' => $this->ip_address,
      'mac_address' => $this->mac_address,
      'serial_number' => $this->serial_number,
      'end_of_support' => $this->end_of_support,
      'warranty' => $this->warranty,
      'firmware_version' => $this->firmware_version,
      'location' => $this->location,
      'floor' => $this->floor,
      'tower' => $this->tower,
      'credential_id' => $this->credential_id ?: null,
      'remark' => $this->remark,
    ]);

    $this->dispatch('asset-modal:close');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'Switch berhasil diupdate.']);

    $this->resetForm();
    $this->resetPage();
  }

  public function delete($id): void
  {
    AssetSwitch::findOrFail($id)->delete();

    $this->dispatch('toast', ['type' => 'success', 'message' => 'Switch berhasil dihapus.']);
    $this->resetPage();
  }
  private function formatMac(?string $val): string
  {
    // 1) uppercase dulu biar a-f jadi A-F
    $v = strtoupper((string) $val);

    // 2) baru buang semua selain hex (0-9, A-F)
    $v = preg_replace('/[^0-9A-F]/', '', $v);

    // 3) max 12 hex chars
    $v = substr($v, 0, 12);

    // 4) kelompokkan per 2 char
    $parts = str_split($v, 2);

    return implode(':', $parts);
  }
  private function normalizeDates(): void
  {
    $this->end_of_support = $this->blankToNull($this->end_of_support);
    $this->warranty = $this->blankToNull($this->warranty);
  }

  private function blankToNull($v): ?string
  {
    if ($v === null)
      return null;
    $v = trim((string) $v);
    return $v === '' ? null : $v;
  }
  public function closeModal(): void
  {
    $this->dispatch('asset-modal:close');
    $this->resetForm();
  }

  public function render()
  {
    $sortField = in_array($this->sortField, $this->sortable, true) ? $this->sortField : 'id';
    $sortDir = $this->sortDirection === 'asc' ? 'asc' : 'desc';

    $assets = $this->baseQuery()
      ->orderBy($sortField, $sortDir)
      // stabilizer biar pagination gak “loncat” saat banyak nilai sama
      ->orderBy('id', 'desc')
      ->paginate($this->perPage)
      ->withQueryString();

    return view('livewire.device.network-switch', [
      'assets' => $assets,
    ]);
  }
}
