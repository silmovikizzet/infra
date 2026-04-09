<?php

namespace App\Livewire\Network;

use Livewire\Component;
use App\Models\Vlan;
use App\Models\Asset;
use phpseclib3\Net\SSH2;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('Login Basic - Pages')]
class VlanSite extends Component
{
  public $id = '';
  public $vlans = [];
  public $vlan_id = '';
  public $nama = '';
  public $network = '';
  public $gateway = '';
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
  protected $rules = [
    'vlan_id' => 'required|integer',
    'nama' => 'required|string|max:100',
    'network' => [
      'required',
      'regex:/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}'
      . '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\/([0-9]|[1-2][0-9]|3[0-2])$/'
    ],
    'gateway' => 'required|ip',
    'remark' => 'nullable|string|max:255',
    'dhcp' => 'nullable|string|max:100',
  ];

  public function mount(): void
  {
    if (!auth()->check()) {
      $this->redirect(route('login'), navigate: true);
      return;
    }

    $this->site = request()->query('site', null);
    $this->site = is_string($this->site) ? trim(preg_replace('/\s+/', ' ', $this->site)) : null;

    $this->search = (string) request()->query('q', '');
    $this->search = trim($this->search);
    $this->loadSiteOptions();
    $this->refreshList();
  }
  private function loadSiteOptions(): void
  {
    $user = auth()->user();

    $raw = $user ? $user->sites()->pluck('site')->toArray() : [];

    $clean = array_values(array_unique(array_filter(array_map(function ($s) {
      $s = trim((string) $s);
      $s = preg_replace('/\s+/', ' ', $s);
      return $s;
    }, $raw))));

    sort($clean);
    $this->siteOptions = $clean;
  }



  public function refreshList(): void
  {
    $query = Vlan::query();

    $user = auth()->user();
    $userSites = $user ? $user->sites()->pluck('site')->toArray() : [];

    // ✅ selalu batasi data sesuai site user
    if (!empty($userSites)) {
      $query->whereIn('site', $userSites);
    } else {
      // kalau user tidak punya mapping site, kosongkan list (opsional)
      $this->vlans = collect();
      return;
    }

    // ✅ filter tambahan: hanya site yg dipilih
    if (!empty($this->site)) {
      $query->where('site', $this->site);
    }
    // ✅ search filter
    $term = trim((string) $this->search);
    if ($term !== '') {
      $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

      $query->where(function ($q) use ($like, $term) {
        // kalau user ngetik angka, cocokkan vlan_id juga
        if (ctype_digit($term)) {
          $q->orWhere('vlan_id', (int) $term);
        }

        $q->orWhere('nama', 'like', $like)
          ->orWhere('network', 'like', $like)
          ->orWhere('gateway', 'like', $like)
          ->orWhere('remark', 'like', $like)
          ->orWhere('dhcp', 'like', $like);
      });
    }

    $this->vlans = $query->orderBy('vlan_id', 'asc')->get();

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

    $this->refreshList();
  }
  public function updatedSearch($val): void
  {
    $this->search = trim((string) $val);
    $this->refreshList();
  }
  // Recalculate when user types network
  public function updatedNetwork($value)
  {
    if (trim($value) === '') {
      $this->client = $this->start_ip = $this->last_ip = null;
      return;
    }
    try {
      $this->calculateFromCidr($value);
    } catch (\Throwable $e) {
      // ignore calculation errors; validation will catch invalid format
      $this->client = $this->start_ip = $this->last_ip = null;
    }
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

  public function resetForm()
  {
    $this->vlan_id = null;
    $this->nama = $this->network = $this->gateway = $this->remark = $this->dhcp = '';
    $this->client = $this->start_ip = $this->last_ip = null;
    $this->isEdit = false;
  }

  public function store()
  {
    $this->validate();

    // ensure calculation is up-to-date
    $this->calculateFromCidr($this->network);

    Vlan::create([
      'dhcp' => $this->dhcp,
      'vlan_id' => $this->vlan_id,
      'nama' => $this->nama,
      'network' => $this->network,
      'gateway' => $this->gateway,
      'remark' => $this->remark,
      'client' => $this->client,
      'start_ip' => $this->start_ip,
      'last_ip' => $this->last_ip,
      'site' => $this->site
    ]);

    $this->dispatch('toast', ['type' => 'success', 'message' => 'VLAN created']);
    $this->resetForm();
    $this->refreshList();
  }
  public function edit($id)
  {
    $vlan = Vlan::findOrFail($id);
    $this->dhcp = $vlan->dhcp;
    $this->id = $vlan->id;
    $this->vlan_id = $vlan->vlan_id;
    $this->nama = $vlan->nama;
    $this->network = $vlan->network;
    $this->gateway = $vlan->gateway;
    $this->remark = $vlan->remark;
    $this->client = $vlan->client;
    $this->start_ip = $vlan->start_ip;
    $this->last_ip = $vlan->last_ip;
    $this->isEdit = true;
    $this->showModal = true;
  }
  public function openCreate()
  {
    $this->isEdit = false;
    $this->showModal = true;
  }

  public function check($id)
  {
    Log::info("🔎 Check VLAN called", ['vlan_id' => $id]);

    $vlan = Vlan::findOrFail($id);
    $this->checkModal = true;

    Log::info("✅ VLAN found", ['site' => $vlan->site]);

    // cari lokasi vlan
    $location = $vlan->site;

    // cari core switch di lokasi yang sama
    $coreSwitch = Asset::where('location', $location)
      ->where('group', 'core') // sesuaikan fieldnya
      ->first();

    if (!$coreSwitch) {
      Log::warning("⚠️ No Core switch found", ['location' => $location]);
      $this->dispatch('toast', ['type' => 'error', 'message' => 'No Core switch found']);
      return;
    }

    Log::info("✅ Core switch found", [
      'ip' => $coreSwitch->ip_address,
      'port' => $coreSwitch->credential->port ?? 22,
      'username' => $coreSwitch->credential->username
    ]);

    // koneksi ssh ke core switch
    $ssh = new SSH2($coreSwitch->ip_address, $coreSwitch->credential->port ?? 22);
    $password = Crypt::decryptString($coreSwitch->credential->password);
    if (!$ssh->login($coreSwitch->credential->username, $password)) {
      Log::error("❌ Failed to connect SSH", [
        'ip' => $coreSwitch->ip_address,
        'port' => $coreSwitch->credential->port ?? 22
      ]);
      $this->dispatch('toast', [
        'type' => 'error',
        'message' => 'Failed to connect SSH'
      ]);
      return;
    }

    Log::info("✅ SSH login success, running command");

    // jalankan perintah show arp
    $output = $ssh->exec('display arp vlan ' . $vlan->vlan_id);

    Log::info("📄 Command output received", [
      'length' => strlen($output),
      'preview' => substr($output, 0, 200) // biar gak penuh di log
    ]);

    // simpan output agar bisa ditampilkan di modal
    $this->arpResult = $output;
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
      'dhcp' => $this->dhcp,
      'vlan_id' => $this->vlan_id,
      'nama' => $this->nama,
      'network' => $this->network,
      'gateway' => $this->gateway,
      'remark' => $this->remark,
      'client' => $this->client,
      'start_ip' => $this->start_ip,
      'last_ip' => $this->last_ip,
    ]);

    $this->dispatch('toast', ['type' => 'success', 'message' => 'VLAN updated']);
    $this->resetForm();
    $this->refreshList();
  }

  public function delete($id)
  {
    $vlan = Vlan::findOrFail($id)->delete();
    $this->dispatch('toast', ['type' => 'success', 'message' => 'VLAN deleted']);
    $this->refreshList();
  }
  public function closeModal()
  {
    $this->showModal = false;
    $this->resetForm();
  }
  public function render()
  {
    return view('livewire.network.vlan-site');
  }
}
