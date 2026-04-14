<?php

namespace App\Livewire\Network;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\IpAddress;
use App\Models\Vlan;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('IP Address - Network')]
class IpSite extends Component
{
  public $id = '';
  public $rows = [];

  // filter utama
  public $site = null;
  public array $siteOptions = [];

  public $vlan_id = null; // filter VLAN (optional)
  public array $vlanOptions = [];

  public $search = '';

  // form fields
  public $form_site = null;
  public $ip = '';
  public $description = '';

  public $isEdit = false;

  protected function rules(): array
  {
    $id = $this->id ?: null;

    return [
      'form_site' => ['required', 'string', 'max:150'],

      'ip' => ['required', 'ip', 'max:255', 'unique:ip_addresses,ip,' . ($id ?? 'NULL') . ',id'],
      'description' => ['required', 'string', 'max:255', 'unique:ip_addresses,description,' . ($id ?? 'NULL') . ',id'],

      'site' => ['nullable', 'string', 'max:150'],
      'vlan_id' => ['nullable', 'integer'],
      'search' => ['nullable', 'string', 'max:200'],
    ];
  }

  protected array $messages = [
    'form_site.required' => 'Site wajib dipilih.',

    'ip.required' => 'IP wajib diisi.',
    'ip.ip' => 'Format IP tidak valid.',
    'ip.unique' => 'IP sudah dipakai.',

    'description.required' => 'Deskripsi wajib diisi.',
    'description.unique' => 'Deskripsi harus unik (sudah dipakai).',
  ];

  public function mount(): void
  {
    $this->loadSiteOptions();
    $this->loadVlanOptions();
    $this->refreshList();
  }
  private function loadSiteOptions(): void
  {
    $user = auth()->user();

    $rows = $user
      ? $user->sites()
        ->select(['id', 'site'])
        ->orderBy('id', 'asc')
        ->get()
      : collect();

    $seen = [];
    $out = [];

    foreach ($rows as $row) {
      $s = trim((string) ($row->site ?? ''));
      $s = preg_replace('/\s+/', ' ', $s);

      if ($s === '') {
        continue;
      }

      if (isset($seen[$s])) {
        continue;
      }

      $seen[$s] = true;
      $out[] = $s;
    }

    $this->siteOptions = $out;
  }
  private function loadVlanOptions(): void
  {
    $this->vlanOptions = [];

    $site = trim((string) $this->site);
    if ($site === '') {
      return;
    }

    $user = auth()->user();
    if (!$user) {
      return;
    }

    $allowedSites = $user->sites()
      ->pluck('site')
      ->map(fn($s) => trim((string) $s))
      ->filter()
      ->unique()
      ->values()
      ->all();

    if (!in_array($site, $allowedSites, true)) {
      $this->site = '';
      $this->vlan_id = '';
      return;
    }

    $rows = Vlan::query()
      ->where('site', $site)
      ->orderBy('vlan_id')
      ->get(['id', 'name', 'vlan_id']);

    $this->vlanOptions = $rows
      ->map(function ($row) {
        $name = trim((string) ($row->name ?? ''));
        $vlanId = (string) ($row->vlan_id ?? '');

        return [
          'id' => $row->id,
          'label' => $name !== ''
            ? $name . ' / VLAN ' . $vlanId
            : 'VLAN ' . $vlanId,
        ];
      })
      ->values()
      ->all();
  }
  public function updatedSite($value): void
  {
    $this->site = trim((string) $value);
    $this->vlan_id = '';
    $this->loadVlanOptions();
    $this->refreshList();
  }

  public function updatedSearch($val): void
  {
    $this->search = trim((string) $val);
    $this->refreshList();
  }

  public function updatedVlanId($value): void
  {
    $this->vlan_id = trim((string) $value);
    $this->refreshList();
  }


  public function updatedFormSite($val): void
  {
    $val = is_string($val) ? trim(preg_replace('/\s+/', ' ', $val)) : null;
    $val = $val === '' ? null : $val;

    $this->form_site = $val;

  }
  private function ipv4ToLong(?string $ip): ?int
  {
    $ip = trim((string) $ip);
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      return null;
    }

    $n = ip2long($ip);
    if ($n === false)
      return null;

    // convert ke unsigned 32-bit
    return (int) sprintf('%u', $n);
  }

  private function cidrToRange(?string $cidr): ?array
  {
    $cidr = trim((string) $cidr);
    if ($cidr === '' || !str_contains($cidr, '/'))
      return null;

    [$ip, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
    $ip = trim((string) $ip);
    $prefix = (int) trim((string) $prefix);

    $ipLong = $this->ipv4ToLong($ip);
    if ($ipLong === null || $prefix < 0 || $prefix > 32)
      return null;

    $mask = $prefix === 0 ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);
    $mask = (int) sprintf('%u', $mask);

    $network = $ipLong & $mask;
    $broadcast = $network + (2 ** (32 - $prefix)) - 1;

    return [
      'start' => (int) $network,
      'end' => (int) $broadcast,
      'prefix' => (int) $prefix,
    ];
  }

  /**
   * Deteksi VLAN berdasarkan IP + site dari kolom vlans.network (CIDR).
   * - Mengambil semua VLAN pada site tsb
   * - Pilih yang paling spesifik (prefix terbesar) jika overlap
   */
  private function detectVlanByIpAndSite(string $ip, string $site): ?Vlan
  {
    $ipLong = $this->ipv4ToLong($ip);
    if ($ipLong === null)
      return null;

    $cands = Vlan::query()
      ->where('site', $site)
      ->select(['id', 'site', 'vlan_id', 'name', 'network'])
      ->get()
      ->map(function ($v) {
        $rng = $this->cidrToRange($v->network);
        if (!$rng)
          return null;

        return [
          'model' => $v,
          'start' => $rng['start'],
          'end' => $rng['end'],
          'prefix' => $rng['prefix'],
        ];
      })
      ->filter()
      ->sortByDesc('prefix') // paling spesifik dulu
      ->values();

    foreach ($cands as $c) {
      if ($ipLong >= $c['start'] && $ipLong <= $c['end']) {
        return $c['model'];
      }
    }

    return null;
  }
  public function refreshList(): void
  {
    $allowedSites = $this->allowedSites();

    $query = IpAddress::query()
      ->with('vlan')
      ->whereHas('vlan', function ($q) use ($allowedSites) {
        if (empty($allowedSites)) {
          $q->whereRaw('1 = 0');
          return;
        }

        $q->whereIn('site', $allowedSites);
      });

    // filter site terpilih
    if (!empty($this->site)) {
      $query->whereHas('vlan', function ($q) {
        $q->where('site', $this->site);
      });
    }

    // filter vlan terpilih
    if (!empty($this->vlan_id)) {
      $query->where('vlan_id', (int) $this->vlan_id);
    }

    $term = trim((string) $this->search);
    if ($term !== '') {
      $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

      $query->where(function ($q) use ($like) {
        $q->where('ip', 'like', $like)
          ->orWhere('description', 'like', $like)
          ->orWhereHas('vlan', function ($vq) use ($like) {
            $vq->where('name', 'like', $like)
              ->orWhere('vlan_id', 'like', $like)
              ->orWhere('site', 'like', $like);
          });
      });
    }

    $rows = $query->orderBy('id', 'desc')->get();

    foreach ($rows as $r) {
      $site = (string) ($r->vlan->site ?? '');
      $guess = null;

      if ($site !== '') {
        $guess = $this->detectVlanByIpAndSite((string) $r->ip, $site);
      }

      $r->vlan_guess = $guess;
    }

    $this->rows = $rows;
  }

  public function resetForm(): void
  {
    $this->id = '';
    $this->isEdit = false;

    $this->form_site = null;
    $this->ip = '';
    $this->description = '';
  }

  public function openCreate(): void
  {
    $this->resetErrorBag();
    $this->resetValidation();
    $this->resetForm();
    $this->isEdit = false;

    if (!empty($this->site)) {
      $this->form_site = $this->site;
    }

    $this->dispatch('ip-address-modal:open');
  }

  public function edit($id): void
  {
    $this->resetErrorBag();
    $this->resetValidation();

    $row = IpAddress::with('vlan')->findOrFail($id);

    $this->id = (string) $row->id;
    $this->isEdit = true;

    $this->ip = (string) $row->ip;
    $this->description = (string) $row->description;

    // site untuk modal (ambil dari relasi vlan yg tersimpan)
    $this->form_site = (string) ($row->vlan->site ?? '');

    $this->dispatch('ip-address-modal:open');
  }

  public function store(): void
  {
    $this->validate();

    $site = trim((string) $this->form_site);
    $ip = trim((string) $this->ip);

    $vlan = $this->detectVlanByIpAndSite($ip, $site);
    if (!$vlan) {
      $this->addError('ip', 'IP tidak masuk network VLAN manapun pada site ini.');
      return;
    }

    IpAddress::create([
      'ip' => $ip,
      'description' => trim((string) $this->description),
      'vlan_id' => (int) $vlan->id, // ✅ auto set dari guess
    ]);

    $this->dispatch('toast', ['type' => 'success', 'message' => 'IP Address berhasil dibuat.']);
    $this->resetForm();
    $this->refreshList();
    $this->dispatch('ip-address-modal:close');
  }
  private function allowedSites(): array
  {
    $user = auth()->user();

    if (!$user) {
      return [];
    }

    return $user->sites()
      ->pluck('site')
      ->map(fn($s) => trim(preg_replace('/\s+/', ' ', (string) $s)))
      ->filter()
      ->unique()
      ->values()
      ->all();
  }
  public function update(): void
  {
    if (!$this->id) {
      $this->dispatch('toast', ['type' => 'error', 'message' => 'Tidak ada IP Address yang dipilih.']);
      return;
    }

    $this->validate();

    $site = trim((string) $this->form_site);
    $ip = trim((string) $this->ip);

    $vlan = $this->detectVlanByIpAndSite($ip, $site);
    if (!$vlan) {
      $this->addError('ip', 'IP tidak masuk network VLAN manapun pada site ini.');
      return;
    }

    $row = IpAddress::findOrFail($this->id);
    $row->update([
      'ip' => $ip,
      'description' => trim((string) $this->description),
      'vlan_id' => (int) $vlan->id, // ✅ auto update dari guess
    ]);

    $this->dispatch('toast', ['type' => 'success', 'message' => 'IP Address berhasil diupdate.']);
    $this->resetForm();
    $this->refreshList();
    $this->dispatch('ip-address-modal:close');
  }

  public function delete($id): void
  {
    IpAddress::findOrFail($id)->delete();
    $this->dispatch('toast', ['type' => 'success', 'message' => 'IP Address berhasil dihapus.']);
    $this->refreshList();
  }

  public function closeModal(): void
  {
    $this->dispatch('ip-address-modal:close');
    $this->resetForm();
    $this->resetErrorBag();
    $this->resetValidation();
  }

  public function render()
  {
    return view('livewire.network.ip-site');
  }
}
