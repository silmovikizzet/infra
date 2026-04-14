<?php

namespace App\Livewire\Network;

use Livewire\Component;
use App\Models\Vlan;
use App\Models\AssetSwitch;
use App\Models\DhcpPool;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('VLAN - Network')]
class VlanSite extends Component
{
  public $id = '';
  public $vlans = [];
  public $vlan_id = '';
  public $name = '';
  public $network = '';
  public $gateway = '';
  public $netmask = '';
  public $remark = '';
  public $client = null;
  public $start_ip = null;
  public $last_ip = null;
  public $isEdit = false;
  public $site = null;
  public $dhcp = '';
  public $showModal = false;
  public $checkModal = false;
  public $search = '';
  public $arpResult = '';
  public array $siteOptions = [];
  public $dhcp_pool_id = null;      // nullable: null = static
  public array $dhcpPoolOptions = []; // dropdown options
  public $filterSite = null; // ✅ khusus filter list (header)
  private bool $syncing = false;
  protected $rules = [
    'vlan_id' => 'required|integer',
    'name' => 'required|string|max:100',
    'network' => [
      'required',
      'regex:/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\/([0-9]|[1-2][0-9]|3[0-2])$/'
    ],
    'netmask' => 'nullable|ip', // untuk input, bukan DB
    'gateway' => 'required|ip',
    'remark' => 'nullable|string|max:255',
    'dhcp_pool_id' => 'nullable|integer|exists:dhcp_pools,id',
  ];


  protected array $messages = [
    'vlan_id.required' => 'VLAN ID wajib diisi.',
    'vlan_id.integer' => 'VLAN ID harus berupa angka.',

    'name.required' => 'Nama VLAN wajib diisi.',
    'name.string' => 'Nama VLAN harus berupa teks.',
    'name.max' => 'Nama VLAN maksimal :max karakter.',

    'network.required' => 'Network wajib diisi.',
    'network.regex' => 'Format Network harus CIDR, contoh: 10.1.1.0/24.',

    'gateway.required' => 'Gateway wajib diisi.',
    'gateway.ip' => 'Format Gateway harus berupa alamat IP yang valid. Contoh: 10.1.1.1.',

    'remark.string' => 'Remark harus berupa teks.',
    'remark.max' => 'Remark maksimal :max karakter.',

    'dhcp_pool_id.integer' => 'DHCP Pool tidak valid.',
    'dhcp_pool_id.exists' => 'DHCP Pool tidak ditemukan.',
  ];

  public function mount(): void
  {
    if (!auth()->check()) {
      $this->redirect(route('login'), navigate: true);
      return;
    }

    $this->filterSite = request()->query('site', null);
    $this->filterSite = is_string($this->filterSite)
      ? trim(preg_replace('/\s+/', ' ', $this->filterSite))
      : null;

    $this->filterSite = ($this->filterSite === '') ? null : $this->filterSite;

    $this->search = (string) request()->query('q', '');
    $this->search = trim($this->search);

    $this->loadSiteOptions();

    // form site jangan ikut filter (biar modal kosong saat create)
    $this->site = null;
    $this->loadDhcpPoolOptions();

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

      if ($s === '')
        continue;

      // unique tapi tetap urutan id
      if (isset($seen[$s]))
        continue;
      $seen[$s] = true;

      $out[] = $s;
    }

    $this->siteOptions = $out;
  }

  private function loadDhcpPoolOptions(): void
  {
    $user = auth()->user();

    $userSites = $user
      ? $user->sites()->pluck('site')->map(fn($s) => trim(preg_replace('/\s+/', ' ', (string) $s)))->toArray()
      : [];

    $site = is_string($this->site) ? trim(preg_replace('/\s+/', ' ', $this->site)) : null;

    if ($site === '' || $site === null || !in_array($site, $userSites, true)) {
      $this->dhcpPoolOptions = [];
      // jangan paksa reset kalau lagi edit, tapi kalau kamu mau tetap reset, pakai '' konsisten:
      // $this->dhcp_pool_id = '';
      return;
    }

    $rows = DhcpPool::query()
      ->where('site', $site)
      ->orderBy('id', 'desc')
      ->get(['id', 'name', 'network']);

    $this->dhcpPoolOptions = $rows->map(fn($r) => [
      'id' => (string) $r->id, // <-- stringkan
      'label' => trim($r->name . ' — ' . $r->network),
    ])->toArray();

    // kalau terpilih tapi tidak ada di list, reset ke static
    if ($this->dhcp_pool_id !== '' && $this->dhcp_pool_id !== null) {
      $ok = collect($this->dhcpPoolOptions)->contains('id', (string) $this->dhcp_pool_id);
      if (!$ok)
        $this->dhcp_pool_id = '';
    }
  }

  public function refreshList(): void
  {
    $query = Vlan::query();

    $user = auth()->user();
    $userSites = $user ? $user->sites()->pluck('site')->toArray() : [];

    if (!empty($userSites)) {
      $query->whereIn('site', $userSites);
    } else {
      $this->vlans = collect();
      return;
    }

    // ✅ filter tambahan pakai filterSite
    if (!empty($this->filterSite)) {
      $query->where('site', $this->filterSite);
    }

    $term = trim((string) $this->search);
    if ($term !== '') {
      $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

      $query->where(function ($q) use ($like, $term) {
        if (ctype_digit($term)) {
          $q->orWhere('vlan_id', (int) $term);
        }

        $q->orWhere('name', 'like', $like)
          ->orWhere('network', 'like', $like)
          ->orWhere('gateway', 'like', $like)
          ->orWhere('remark', 'like', $like);
      });
    }

    $this->vlans = $query->orderBy('vlan_id', 'asc')->get();
  }
  public function updatedFilterSite($val): void
  {
    $val = is_string($val) ? trim(preg_replace('/\s+/', ' ', $val)) : null;
    $val = $val === '' ? null : $val;

    if ($val !== null && !in_array($val, $this->siteOptions, true)) {
      $this->filterSite = null;
    } else {
      $this->filterSite = $val;
    }

    $this->refreshList();
  }



  private function cidrToNetmask($cidr)
  {
    return long2ip(-1 << (32 - (int) $cidr));
  }

  public function updatedSite($val): void
  {
    $val = is_string($val) ? trim(preg_replace('/\s+/', ' ', $val)) : null;
    $val = $val === '' ? null : $val;

    if ($val !== null && !in_array($val, $this->siteOptions, true)) {
      $this->site = null;
    } else {
      $this->site = $val;
    }

    $this->loadDhcpPoolOptions();
  }

  public function updatedSearch($val): void
  {
    $this->search = trim((string) $val);
    $this->refreshList();
  }
  // Recalculate when user types network
  public function updatedNetwork($value): void
  {
    if ($this->syncing)
      return;
    $this->syncing = true;

    $value = trim((string) $value);

    if ($value === '') {
      $this->netmask = '';
      $this->client = $this->start_ip = $this->last_ip = null; // ✅ reset live
      $this->syncing = false;
      return;
    }

    if (str_contains($value, '/')) {
      [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, '');
      $ip = trim($ip);
      $prefix = (int) trim($prefix);

      if ($this->isValidIpv4($ip) && $prefix >= 0 && $prefix <= 32) {
        $this->netmask = $this->prefixToNetmask($prefix);
      } else {
        $this->netmask = '';
      }

      $this->syncing = false;

      $this->recalcClientRange(); // ✅ HITUNG LIVE
      return;
    }

    if ($this->isValidIpv4($value)) {
      $prefix = $this->netmaskToPrefix($this->netmask);
      if ($prefix !== null) {
        $this->network = $this->setNetworkPrefix($value, $prefix);
        // begitu jadi CIDR, recalc akan kepanggil via updatedNetwork lagi,
        // tapi aman karena syncing true saat setNetworkPrefix
      }
    }

    $this->syncing = false;
  }


  public function updatedNetmask($value): void
  {
    if ($this->syncing)
      return;
    $this->syncing = true;

    $mask = trim((string) $value);
    if ($mask === '') {
      $this->syncing = false;
      return;
    }

    $prefix = $this->netmaskToPrefix($mask);
    if ($prefix === null) {
      $this->syncing = false;
      return;
    }

    $net = trim((string) $this->network);
    if ($net === '') {
      $this->client = $this->start_ip = $this->last_ip = null; // ✅ reset
      $this->syncing = false;
      return;
    }

    if (str_contains($net, '/')) {
      [$ip] = explode('/', $net, 2);
      $ip = trim($ip);
      if ($this->isValidIpv4($ip)) {
        $this->network = $this->setNetworkPrefix($ip, $prefix);
      }
    } elseif ($this->isValidIpv4($net)) {
      $this->network = $this->setNetworkPrefix($net, $prefix);
    }

    $this->syncing = false;

    $this->recalcClientRange(); // ✅ HITUNG LIVE
  }

  private function isValidIpv4(string $ip): bool
  {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
  }

  private function prefixToNetmask(int $prefix): string
  {
    $mask = ($prefix === 0) ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);
    return long2ip((int) sprintf('%u', $mask));
  }

  private function netmaskToPrefix(?string $mask): ?int
  {
    $mask = trim((string) $mask);
    if ($mask === '' || !$this->isValidIpv4($mask))
      return null;

    $long = ip2long($mask);
    if ($long === false)
      return null;

    // pastikan netmask contiguous (111..1100..00)
    $bin = sprintf('%032b', (int) sprintf('%u', $long));
    if (!preg_match('/^1*0*$/', $bin))
      return null;

    return substr_count($bin, '1');
  }

  private function setNetworkPrefix(string $ip, int $prefix): string
  {
    return $ip . '/' . $prefix;
  }

  private function netmaskFromNetwork(?string $network): ?string
  {
    $network = trim((string) $network);
    if ($network === '' || !str_contains($network, '/'))
      return null;

    [, $prefix] = explode('/', $network, 2);
    $prefix = (int) trim($prefix);

    if ($prefix < 0 || $prefix > 32)
      return null;
    return $this->prefixToNetmask($prefix);
  }

  private function calculateFromCidr(string $cidr)
  {
    if (!str_contains($cidr, '/')) {
      throw new \Exception('Invalid CIDR');
    }
    [$ip, $prefix] = explode('/', $cidr);
    $prefix = (int) $prefix;

    $ipLong = ip2long($ip);
    if ($ipLong === false)
      throw new \Exception('Invalid IP');

    // calculate network mask
    $mask = ($prefix === 0) ? 0 : (~0 << (32 - $prefix));
    // ensure mask behaves as unsigned 32-bit
    $mask = $mask & 0xFFFFFFFF;

    $network = $ipLong & $mask;
    $broadcast = $network + (pow(2, (32 - $prefix)) - 1);

    // for very small networks (/31 /32) usable hosts may be 0
    $firstHost = $network + 1;
    $lastHost = $broadcast - 1;

    $hostCount = 0;
    if ($lastHost >= $firstHost) {
      $hostCount = $lastHost - $firstHost + 1;
    } else {
      $hostCount = 0;
      // for /31 there are 2 addresses but typically no usable host if we treat network/broadcast
      // keep hostCount 0 so UI shows 0
    }

    $this->client = $hostCount;
    $this->start_ip = ($hostCount > 0) ? long2ip($firstHost) : null;
    $this->last_ip = ($hostCount > 0) ? long2ip($lastHost) : null;
  }
  private function recalcClientRange(): void
  {
    $cidr = trim((string) $this->network);

    if ($cidr === '' || !str_contains($cidr, '/')) {
      $this->client = $this->start_ip = $this->last_ip = null;
      return;
    }

    // validasi ringan biar gak throw terus
    [$ip, $prefix] = array_pad(explode('/', $cidr, 2), 2, '');
    $ip = trim($ip);
    $prefix = (int) trim($prefix);

    if (!$this->isValidIpv4($ip) || $prefix < 0 || $prefix > 32) {
      $this->client = $this->start_ip = $this->last_ip = null;
      return;
    }

    $this->calculateFromCidr($cidr);
  }

  public function resetForm()
  {
    $this->vlan_id = null;
    $this->name = $this->network = $this->gateway = $this->remark = '';
    $this->dhcp_pool_id = null;

    $this->client = $this->start_ip = $this->last_ip = null;
    $this->isEdit = false;

    // ✅ reset site FORM saja, bukan filter
    $this->site = null;

    $this->loadDhcpPoolOptions();
  }


  public function store()
  {
    $this->validate();
    $this->calculateFromCidr($this->network);

    Vlan::create([
      'dhcp_pool_id' => $this->dhcp_pool_id ?: null,
      'vlan_id' => $this->vlan_id,
      'name' => $this->name,
      'network' => $this->network,
      'gateway' => $this->gateway,
      'remark' => $this->remark,
      'site' => $this->site, // ✅ form site
    ]);

    $this->dispatch('vlan-modal:close');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'VLAN berhasil dibuat.']);
    $this->resetForm();
    $this->refreshList(); // tetap pakai filterSite
  }

  public function edit($id): void
  {
    $this->resetErrorBag();
    $this->resetValidation();

    $vlan = Vlan::findOrFail($id);

    $this->id = (string) $vlan->id;
    $this->isEdit = true;

    $this->site = is_string($vlan->site) ? trim(preg_replace('/\s+/', ' ', $vlan->site)) : null;

    // set dulu value select
    $this->dhcp_pool_id = $vlan->dhcp_pool_id ? (string) $vlan->dhcp_pool_id : '';

    $this->vlan_id = $vlan->vlan_id;
    $this->name = $vlan->name;
    $this->network = $vlan->network;
    $this->gateway = $vlan->gateway;
    $this->remark = $vlan->remark;

    // baru load options setelah site & dhcp_pool_id sudah terisi
    $this->loadDhcpPoolOptions();

    $this->netmask = $this->netmaskFromNetwork($this->network) ?? '';
    $this->calculateFromCidr($this->network);

    $this->dispatch('vlan-modal:open');
  }

  public function openCreate(): void
  {
    $this->resetErrorBag();
    $this->resetValidation();
    $this->resetForm();
    $this->isEdit = false;
    $this->netmask = $this->netmaskFromNetwork($this->network) ?? '';

    $this->dispatch('vlan-modal:open');
  }
  public function update()
  {
    if (!$this->id) {
      $this->dispatch('toast', ['type' => 'error', 'message' => 'No VLAN selected to update']);
      return;
    }

    $this->validate();
    $this->calculateFromCidr($this->network);

    $vlan = Vlan::findOrFail($this->id);
    $vlan->update([
      'dhcp_pool_id' => $this->dhcp_pool_id ?: null,
      'vlan_id' => $this->vlan_id,
      'name' => $this->name,
      'network' => $this->network,
      'gateway' => $this->gateway,
      'remark' => $this->remark,
      'site' => $this->site, // ✅ kalau memang boleh edit site
    ]);

    $this->dispatch('vlan-modal:close');
    $this->dispatch('toast', ['type' => 'success', 'message' => 'VLAN berhasil diupdate.']);

    $this->resetForm();
    $this->refreshList();
  }
  protected function prepareForValidation($attributes)
  {
    // kalau user isi network IP saja, dan netmask valid => convert ke CIDR
    $net = trim((string) ($this->network ?? ''));
    if ($net !== '' && !str_contains($net, '/') && $this->isValidIpv4($net)) {
      $prefix = $this->netmaskToPrefix($this->netmask);
      if ($prefix !== null) {
        $this->network = $this->setNetworkPrefix($net, $prefix);
      }
    }

    // kalau network sudah CIDR, sinkron netmask (biar konsisten)
    if (str_contains((string) $this->network, '/')) {
      $this->netmask = $this->netmaskFromNetwork($this->network) ?? $this->netmask;
    }

    return $attributes;
  }

  public function delete($id)
  {
    $vlan = Vlan::findOrFail($id)->delete();
    $this->dispatch('toast', ['type' => 'success', 'message' => 'VLAN deleted']);
    $this->refreshList();
  }
  public function closeModal()
  {
    $this->dispatch('vlan-modal:close');
    $this->resetForm();
  }
  public function render()
  {
    return view('livewire.network.vlan-site');
  }
}
