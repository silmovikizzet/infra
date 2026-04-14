<?php

namespace App\Livewire\Network;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\DhcpPool;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('DHCP Pool - Network')]
class DhcpSite extends Component
{
  public $id = '';
  public $rows = [];

  public $site = null;
  public array $siteOptions = [];

  public $search = '';

  // form fields
  public $name = '';
  public $network = '';         // disarankan CIDR: 10.2.86.0/23
  public $netmask = '';         // optional, auto dari CIDR
  public $dns_list_text = '';   // "10.2.0.251 172.16.0.252"
  public $gateway_list_text = '';
  public $forbidden_ip = '';
  public $lease_days = 0;
  public $lease_hours = 0;
  public $lease_minutes = 0;
  public $lease_seconds = 0;
  public $option_43_text = '';  // "192.168.200.120 192.168.200.122 ..."
  public $remark = '';
  public bool $syncing = false;
  public $isEdit = false;
  public $showModal = false;

  protected function rules(): array
  {
    return [
      'site' => ['required', 'string', 'max:150'],
      'name' => ['required', 'string', 'max:100'],
      'network' => [
        'required',
        'regex:/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\/([0-9]|[1-2][0-9]|3[0-2]))?$/'
      ],
      'netmask' => ['nullable', 'string', 'max:50'],
      'dns_list_text' => ['required', 'string', 'max:1000'],
      'gateway_list_text' => ['required', 'string', 'max:1000'],
      'forbidden_ip' => ['nullable', 'ip'],
      'lease_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
      'lease_hours' => ['nullable', 'integer', 'min:0', 'max:23'],
      'lease_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
      'lease_seconds' => ['nullable', 'integer', 'min:0', 'max:59'],
      'option_43_text' => ['nullable', 'string', 'max:2000'],
      'remark' => ['nullable', 'string', 'max:5000'],
    ];
  }

  protected array $messages = [
    'name.required' => 'Nama Pool wajib diisi.',
    'name.max' => 'Nama Pool maksimal :max karakter.',

    'network.required' => 'Network wajib diisi.',
    'network.regex' => 'Format Network harus CIDR, contoh: 10.2.86.0/23.',

    'forbidden_ip.ip' => 'Forbidden IP harus berupa alamat IP yang valid.',

    'lease_hours.max' => 'Jam maksimal 23.',
    'lease_minutes.max' => 'Menit maksimal 59.',
    'lease_seconds.max' => 'Detik maksimal 59.',
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

      if (isset($seen[$s]))
        continue;
      $seen[$s] = true;

      $out[] = $s;
    }

    $this->siteOptions = $out;
  }
  private function normalizeIpv4(string $ip): string
  {
    return trim($ip);
  }

  private function isValidIpv4(string $ip): bool
  {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
  }

  private function isValidNetmask(string $mask): bool
  {
    if (!$this->isValidIpv4($mask))
      return false;

    $long = ip2long($mask);
    if ($long === false)
      return false;

    // pastikan contiguous 1-bits lalu 0-bits (netmask valid)
    $u = (int) sprintf('%u', $long);
    $inv = (~$u) & 0xFFFFFFFF;

    // inv harus berbentuk 00..0011..11 (contiguous)
    return (($inv + 1) & $inv) === 0;
  }

  private function netmaskToPrefix(string $mask): ?int
  {
    if (!$this->isValidNetmask($mask))
      return null;

    $u = (int) sprintf('%u', ip2long($mask));
    $bits = 0;
    for ($i = 31; $i >= 0; $i--) {
      if (($u & (1 << $i)) !== 0)
        $bits++;
      else
        break;
    }
    return $bits; // 0..32
  }

  private function setNetworkPrefix(string $ip, int $prefix): void
  {
    $this->network = $ip . '/' . $prefix;
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

  public function updatedNetwork($value): void
  {
    if ($this->syncing)
      return;
    $this->syncing = true;

    $value = trim((string) $value);
    if ($value === '') {
      $this->netmask = '';
      $this->syncing = false;
      return;
    }

    // bentuk: IP/prefix
    if (str_contains($value, '/')) {
      [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, '');
      $ip = trim($ip);
      $prefix = (int) trim($prefix);

      if ($this->isValidIpv4($ip) && $prefix >= 0 && $prefix <= 32) {
        $this->netmask = $this->prefixToNetmask($prefix);
      } else {
        // invalid → jangan paksa apa-apa
        $this->netmask = '';
      }

      $this->syncing = false;
      return;
    }

    // bentuk: IP saja → kalau netmask sudah valid, ubah network jadi IP/prefix
    $ip = $value;
    if ($this->isValidIpv4($ip)) {
      $prefix = $this->netmaskToPrefix((string) $this->netmask);
      if ($prefix !== null) {
        $this->setNetworkPrefix($ip, $prefix);
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
      // kalau user hapus netmask, jangan ubah network
      $this->syncing = false;
      return;
    }

    $prefix = $this->netmaskToPrefix($mask);
    if ($prefix === null) {
      // netmask gak valid → jangan ubah network
      $this->syncing = false;
      return;
    }

    $net = trim((string) $this->network);
    if ($net === '') {
      $this->syncing = false;
      return;
    }

    // kalau network sudah CIDR, ganti prefix-nya
    if (str_contains($net, '/')) {
      [$ip] = explode('/', $net, 2);
      $ip = trim($ip);
      if ($this->isValidIpv4($ip)) {
        $this->setNetworkPrefix($ip, $prefix);
      }
      $this->syncing = false;
      return;
    }

    // kalau network cuma IP, jadikan CIDR
    if ($this->isValidIpv4($net)) {
      $this->setNetworkPrefix($net, $prefix);
    }

    $this->syncing = false;
  }

  private function prefixToNetmask(int $prefix): string
  {
    $mask = ($prefix === 0) ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);
    return long2ip((int) sprintf('%u', $mask));
  }

  private function normalizeIpListText(?string $text): array
  {
    $text = trim((string) $text);
    if ($text === '')
      return [];

    // split by space/comma/newline
    $parts = preg_split('/[\s,]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p === '')
        continue;
      $out[] = $p;
    }
    // unique keep order
    $seen = [];
    $uniq = [];
    foreach ($out as $ip) {
      if (isset($seen[$ip]))
        continue;
      $seen[$ip] = true;
      $uniq[] = $ip;
    }
    return $uniq;
  }

  private function leaseToSeconds(): int
  {
    $d = (int) ($this->lease_days ?? 0);
    $h = (int) ($this->lease_hours ?? 0);
    $m = (int) ($this->lease_minutes ?? 0);
    $s = (int) ($this->lease_seconds ?? 0);

    $d = max(0, $d);
    $h = min(23, max(0, $h));
    $m = min(59, max(0, $m));
    $s = min(59, max(0, $s));

    return ($d * 86400) + ($h * 3600) + ($m * 60) + $s;
  }

  private function secondsToLeaseParts(int $seconds): array
  {
    $seconds = max(0, $seconds);
    $d = intdiv($seconds, 86400);
    $seconds %= 86400;
    $h = intdiv($seconds, 3600);
    $seconds %= 3600;
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;

    return [$d, $h, $m, $s];
  }

  public function refreshList(): void
  {
    $query = DhcpPool::query();

    // batasi sesuai site user (kalau kamu memang pakai field site di dhcp_pools)
    $user = auth()->user();
    $userSites = $user ? $user->sites()->pluck('site')->toArray() : [];

    if (!empty($userSites)) {
      $query->whereIn('site', $userSites);
    } else {
      $this->rows = collect();
      return;
    }

    if (!empty($this->site)) {
      $query->where('site', $this->site);
    }

    $term = trim((string) $this->search);
    if ($term !== '') {
      $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
      $query->where(function ($q) use ($like) {
        $q->orWhere('name', 'like', $like)
          ->orWhere('network', 'like', $like)
          ->orWhere('netmask', 'like', $like)
          ->orWhere('remark', 'like', $like);
      });
    }

    $this->rows = $query->orderBy('id', 'desc')->get();
  }

  public function resetForm(): void
  {
    $this->id = '';
    $this->isEdit = false;

    $this->name = '';
    $this->network = '';
    $this->netmask = '';
    $this->dns_list_text = '';
    $this->gateway_list_text = '';
    $this->forbidden_ip = '';
    $this->lease_days = 0;
    $this->lease_hours = 0;
    $this->lease_minutes = 0;
    $this->lease_seconds = 0;
    $this->option_43_text = '';
    $this->remark = '';
  }

  public function openCreate(): void
  {
    $this->resetErrorBag();
    $this->resetValidation();
    $this->resetForm();
    $this->isEdit = false;

    $this->dispatch('dhcp-pool-modal:open');
  }

  public function edit($id): void
  {
    $this->resetErrorBag();
    $this->resetValidation();

    $row = DhcpPool::findOrFail($id);

    $this->id = (string) $row->id;
    $this->isEdit = true;

    $this->site = $row->site; // optional: kalau kamu mau site bisa diubah, biarin; kalau tidak, hapus inputnya

    $this->name = (string) $row->name;
    $this->network = (string) $row->network;
    $this->netmask = (string) ($row->netmask ?? '');

    $this->dns_list_text = is_array($row->dns_list) ? implode(' ', $row->dns_list) : '';
    $this->gateway_list_text = is_array($row->gateway_list) ? implode(' ', $row->gateway_list) : '';
    $this->forbidden_ip = (string) ($row->forbidden_ip ?? '');

    [$d, $h, $m, $s] = $this->secondsToLeaseParts((int) ($row->lease_seconds ?? 0));
    $this->lease_days = $d;
    $this->lease_hours = $h;
    $this->lease_minutes = $m;
    $this->lease_seconds = $s;

    $this->option_43_text = is_array($row->option) ? implode(' ', $row->option) : '';
    $this->remark = (string) ($row->remark ?? '');

    $this->dispatch('dhcp-pool-modal:open');
  }

  public function store(): void
  {
    $this->validate();

    // pastikan netmask auto kebentuk kalau kosong
    if ($this->netmask === '' && str_contains($this->network, '/')) {
      [, $prefix] = explode('/', $this->network, 2);
      $prefix = (int) $prefix;
      if ($prefix >= 0 && $prefix <= 32) {
        $this->netmask = $this->prefixToNetmask($prefix);
      }
    }

    DhcpPool::create([
      'site' => $this->site,
      'name' => $this->name,
      'network' => $this->network,
      'netmask' => $this->netmask !== '' ? $this->netmask : null,
      'dns_list' => $this->normalizeIpListText($this->dns_list_text),
      'gateway_list' => $this->normalizeIpListText($this->gateway_list_text),
      'forbidden_ip' => $this->forbidden_ip !== '' ? $this->forbidden_ip : null,
      'lease_seconds' => $this->leaseToSeconds(),
      'option' => $this->normalizeIpListText($this->option_43_text),
      'remark' => $this->remark !== '' ? $this->remark : null,
    ]);

    $this->dispatch('toast', ['type' => 'success', 'message' => 'DHCP Pool berhasil dibuat.']);
    $this->resetForm();
    $this->refreshList();
    $this->dispatch('dhcp-pool-modal:close');
  }

  public function update(): void
  {
    if (!$this->id) {
      $this->dispatch('toast', ['type' => 'error', 'message' => 'Tidak ada DHCP Pool yang dipilih.']);
      return;
    }

    $this->validate();

    if ($this->netmask === '' && str_contains($this->network, '/')) {
      [, $prefix] = explode('/', $this->network, 2);
      $prefix = (int) $prefix;
      if ($prefix >= 0 && $prefix <= 32) {
        $this->netmask = $this->prefixToNetmask($prefix);
      }
    }

    $row = DhcpPool::findOrFail($this->id);
    $row->update([
      'site' => $this->site,
      'name' => $this->name,
      'network' => $this->network,
      'netmask' => $this->netmask !== '' ? $this->netmask : null,
      'dns_list' => $this->normalizeIpListText($this->dns_list_text),
      'gateway_list' => $this->normalizeIpListText($this->gateway_list_text),
      'forbidden_ip' => $this->forbidden_ip !== '' ? $this->forbidden_ip : null,
      'lease_seconds' => $this->leaseToSeconds(),
      'option' => $this->normalizeIpListText($this->option_43_text),
      'remark' => $this->remark !== '' ? $this->remark : null,
    ]);

    $this->dispatch('toast', ['type' => 'success', 'message' => 'DHCP Pool berhasil diupdate.']);
    $this->resetForm();
    $this->refreshList();
    $this->dispatch('dhcp-pool-modal:close');
  }

  public function delete($id): void
  {
    DhcpPool::findOrFail($id)->delete();
    $this->dispatch('toast', ['type' => 'success', 'message' => 'DHCP Pool berhasil dihapus.']);
    $this->refreshList();
  }

  public function closeModal(): void
  {
    $this->dispatch('dhcp-pool-modal:close');
    $this->resetForm();
    $this->resetErrorBag();
    $this->resetValidation();
  }

  public function render()
  {
    return view('livewire.network.dhcp-site');
  }
}
