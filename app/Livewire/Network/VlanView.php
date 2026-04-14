<?php

namespace App\Livewire\Network;

use App\Models\Vlan;
use App\Models\Credential;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use phpseclib3\Net\SSH2;
use App\Models\AssetSwitch;
use Illuminate\Support\Facades\Cache;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('VLAN Detail - Network')]
class VlanView extends Component
{
  public int $vlanIdParam;
  public ?AssetSwitch $switch = null;
  public ?Vlan $vlan = null;
  public ?Credential $credential = null;
  public array $deviceInfo = [];
  public bool $isLoadingDeviceVlan = false;
  public bool $deviceVlanLoaded = false;
  public string $deviceVlanError = '';
  public string $sshOutput = '';
  public string $sshError = '';
  public bool $isLoadingRemote = false;
  public bool $remoteLoaded = false;
  public array $networkInfo = [];
  public string $routerHost = '';
  public string $commandPreview = '';
  public string $selectedCommand = 'arp_vlan';
  public array $commandOptions = [];
  public bool $showEditModal = false;

  public string $edit_site = '';
  public string $edit_name = '';
  public string $edit_vlan_id = '';
  public string $edit_network = '';
  public string $edit_netmask = '';
  public string $edit_gateway = '';
  public string $edit_remark = '';
  public bool $deviceVlanFromCache = false;
  public ?string $deviceVlanCachedAt = null;
  public array $siteOptions = [];
  public array $fieldDiffs = [];
  public bool $syncingNetworkMask = false;
  public function mount(int $vlanId): void
  {
    $this->guardAuth();
    $this->vlanIdParam = $vlanId;
    $this->loadSiteOptions();
    $this->loadData();
    $this->loadDeviceVlan();
  }

  private function guardAuth(): void
  {
    if (!auth()->check()) {
      abort(403, 'Unauthorized.');
    }
  }
  protected function rules(): array
  {
    return [
      'edit_site' => ['required', 'string', 'max:255'],
      'edit_name' => ['required', 'string', 'max:255'],
      'edit_vlan_id' => ['required', 'integer', 'min:1', 'max:4094'],
      'edit_network' => [
        'nullable',
        'regex:/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\/([0-9]|[1-2][0-9]|3[0-2]))?$/'
      ],
      'edit_netmask' => ['nullable', 'ip'],
      'edit_gateway' => ['nullable', 'ip'],
      'edit_remark' => ['nullable', 'string'],
    ];
  }

  protected array $messages = [
    'edit_network.regex' => 'Format network harus IP atau CIDR, contoh: 10.2.5.0/24',
    'edit_netmask.ip' => 'Netmask harus IP valid.',
    'edit_gateway.ip' => 'Gateway harus IP valid.',
  ];
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
  private function loadData(): void
  {
    $this->vlan = Vlan::query()->findOrFail($this->vlanIdParam);
    $this->networkInfo = [
      'network' => $this->vlan->network ?: '-',
      'range_ip' => ($this->vlan->start_ip && $this->vlan->last_ip)
        ? $this->vlan->start_ip . ' - ' . $this->vlan->last_ip
        : '-',
      'subnet_mask' => $this->vlan->netmask ?: '-',
      'gateway' => $this->vlan->gateway ?: '-',
      'total_hosts' => $this->vlan->client ?? '-',
      'usable_hosts' => $this->vlan->client ?? '-',
      'first_host' => $this->vlan->start_ip ?: '-',
      'last_host' => $this->vlan->last_ip ?: '-',
    ];
    $this->commandOptions = [
      'arp_vlan' => [
        'label' => 'Cari IP aktif berdasarkan ARP',
        'command' => 'display arp vlan ' . (int) ($this->vlan->vlan_id ?? 0),
      ],
    ];
    $this->commandPreview = $this->commandOptions[$this->selectedCommand]['command'] ?? '';
    $this->switch = AssetSwitch::query()
      ->with('credential')
      ->where('group', 'core')
      ->where('location', trim((string) $this->vlan->site))
      ->whereNotNull('credential_id')
      ->first();

    $this->credential = $this->switch?->credential;

    $this->routerHost = (string) (
      $this->switch?->ip_address
      ?? $this->switch?->hostname
      ?? ''
    );
    $this->deviceInfo = [
      'vlan_id' => '-',
      'vlan_name' => '-',
      'status' => '-',
      'type' => '-',
      'ports' => '-',
      'description' => '-',
    ];
  }
  private function getDisplayVlanCommand(): string
  {
    return 'display vlan ' . (int) ($this->vlan->vlan_id ?? 0);
  }
  private function getDeviceVlanCacheKey(): string
  {
    return 'vlan_device_detail:' . (int) $this->vlanIdParam . ':switch:' . md5((string) $this->routerHost);
  }
  private function fetchDeviceVlanFresh(): array
  {
    if (!$this->vlan) {
      throw new \RuntimeException('Data VLAN tidak ditemukan.');
    }

    if (!$this->credential) {
      throw new \RuntimeException('Credential untuk switch group core tidak ditemukan.');
    }

    $host = trim((string) $this->routerHost);
    $username = trim((string) $this->credential->username);
    $password = (string) $this->credential->password;
    $port = (int) ($this->credential->port ?: 22);

    if ($host === '') {
      throw new \RuntimeException('Host/IP switch core kosong.');
    }

    if ($username === '') {
      throw new \RuntimeException('Username credential kosong.');
    }

    if ($password === '') {
      throw new \RuntimeException('Password credential kosong.');
    }

    $ssh = new SSH2($host, $port, 10);

    if (!$ssh->login($username, $password)) {
      throw new \RuntimeException('Login SSH gagal. Periksa host, username, password, atau port.');
    }

    $command = $this->getDisplayVlanCommand();
    $output = $ssh->exec($command);

    if ($output === false || trim($output) === '') {
      $stderr = method_exists($ssh, 'getStdError') ? trim((string) $ssh->getStdError()) : '';
      throw new \RuntimeException($stderr !== '' ? $stderr : 'Perangkat tidak mengembalikan output VLAN.');
    }

    return $this->parseDisplayVlanOutput((string) $output);
  }
  private function parseDisplayVlanOutput(string $output): array
  {
    $result = [
      'vlan_id' => (string) ($this->vlan->vlan_id ?? '-'),
      'vlan_name' => '-',
      'ipv4_address' => '-',
      'subnet_mask' => '-',
      'status' => '-',
      'type' => '-',
      'ports' => '-',
      'description' => '-',
    ];

    $text = trim($output);

    if (preg_match('/VLAN\s*ID\s*[: ]\s*(\d+)/i', $text, $m)) {
      $result['vlan_id'] = trim($m[1]);
    }

    if (preg_match('/VLAN\s*Name\s*[: ]\s*(.+)/i', $text, $m)) {
      $result['vlan_name'] = trim($m[1]);
    } elseif (preg_match('/Name\s*[: ]\s*(.+)/i', $text, $m)) {
      $result['vlan_name'] = trim($m[1]);
    }

    if (preg_match('/Status\s*[: ]\s*(.+)/i', $text, $m)) {
      $result['status'] = trim($m[1]);
    }

    if (preg_match('/VLAN\s*Type\s*[: ]\s*(.+)/i', $text, $m)) {
      $result['type'] = trim($m[1]);
    } elseif (preg_match('/Type\s*[: ]\s*(.+)/i', $text, $m)) {
      $result['type'] = trim($m[1]);
    }

    if (preg_match('/Description\s*[: ]\s*(.+)/i', $text, $m)) {
      $result['description'] = trim($m[1]);
    }

    if (preg_match('/IPv4\s+address\s*:\s*([0-9]{1,3}(?:\.[0-9]{1,3}){3})/i', $text, $m)) {
      $result['ipv4_address'] = trim($m[1]);
    }

    if (preg_match('/IPv4\s+subnet\s+mask\s*:\s*([0-9]{1,3}(?:\.[0-9]{1,3}){3})/i', $text, $m)) {
      $result['subnet_mask'] = trim($m[1]);
    }

    $ports = [];

    if (preg_match('/Tagged\s+ports?\s*[: ]\s*(.+)/i', $text, $m)) {
      $ports[] = 'Tagged: ' . trim($m[1]);
    }

    if (preg_match('/Untagged\s+ports?\s*[: ]\s*(.+)/i', $text, $m)) {
      $ports[] = 'Untagged: ' . trim($m[1]);
    }

    if (preg_match('/Ports?\s*[: ]\s*(.+)/i', $text, $m) && empty($ports)) {
      $ports[] = trim($m[1]);
    }

    if (!empty($ports)) {
      $result['ports'] = implode(' | ', $ports);
    }

    return $result;
  }
  public function loadDeviceVlan(): void
  {
    $this->guardAuth();

    $this->deviceVlanError = '';
    $this->deviceVlanLoaded = false;
    $this->isLoadingDeviceVlan = true;
    $this->deviceVlanFromCache = false;

    try {
      $cacheKey = $this->getDeviceVlanCacheKey();
      $cached = Cache::get($cacheKey);

      if (is_array($cached)) {
        $this->deviceInfo = $cached['data'] ?? [];
        $this->deviceVlanCachedAt = $cached['cached_at'] ?? null;
        $this->deviceVlanFromCache = true;
      } else {
        $fresh = $this->fetchDeviceVlanFresh();

        $payload = [
          'data' => $fresh,
          'cached_at' => now()->toDateTimeString(),
        ];

        Cache::put($cacheKey, $payload, now()->addHour());

        $this->deviceInfo = $payload['data'];
        $this->deviceVlanCachedAt = $payload['cached_at'];
        $this->deviceVlanFromCache = false;
      }

      $this->deviceVlanLoaded = true;
    } catch (\Throwable $e) {
      $this->deviceVlanError = $e->getMessage();
    } finally {
      $this->isLoadingDeviceVlan = false;
    }
  }
  private function getSelectedCommand(): string
  {
    return $this->commandOptions[$this->selectedCommand]['command'] ?? '';
  }
  public function refreshDeviceVlan(): void
  {
    $this->guardAuth();

    Cache::forget($this->getDeviceVlanCacheKey());

    $this->loadDeviceVlan();
  }
  public function refreshRemote(): void
  {
    $this->guardAuth();

    $this->sshOutput = '';
    $this->sshError = '';
    $this->remoteLoaded = false;
    $this->isLoadingRemote = true;

    try {
      if (!$this->vlan) {
        throw new \RuntimeException('Data VLAN tidak ditemukan.');
      }

      if (!$this->credential) {
        throw new \RuntimeException('Credential untuk switch group core tidak ditemukan.');
      }

      $host = trim((string) $this->routerHost);
      $username = trim((string) $this->credential->username);
      $password = (string) $this->credential->password; // otomatis decrypt dari accessor model
      $port = (int) ($this->credential->port ?: 22);

      if ($host === '') {
        throw new \RuntimeException('Host/IP switch core kosong.');
      }

      if ($username === '') {
        throw new \RuntimeException('Username credential kosong.');
      }

      if ($password === '') {
        throw new \RuntimeException('Password credential kosong.');
      }

      $ssh = new SSH2($host, $port, 10);

      if (!$ssh->login($username, $password)) {
        throw new \RuntimeException('Login SSH gagal. Periksa host, username, password, atau port.');
      }

      $command = $this->getSelectedCommand();

      if ($command === '') {
        throw new \RuntimeException('Command belum dipilih.');
      }
      $output = $ssh->exec($command);

      if ($output === false || trim($output) === '') {
        $stderr = method_exists($ssh, 'getStdError') ? trim((string) $ssh->getStdError()) : '';
        throw new \RuntimeException($stderr !== '' ? $stderr : 'Router tidak mengembalikan output.');
      }

      $this->sshOutput = trim((string) $output);
      $this->remoteLoaded = true;
    } catch (\Throwable $e) {
      $this->sshError = $e->getMessage();
    } finally {
      $this->isLoadingRemote = false;
    }
  }
  public function updatedSelectedCommand(string $value): void
  {
    $this->commandPreview = $this->commandOptions[$value]['command'] ?? '';
  }
  public function isMismatch(?string $dbValue, ?string $deviceValue): bool
  {
    $db = trim((string) $dbValue);
    $device = trim((string) $deviceValue);

    if ($db === '' || $db === '-' || $device === '' || $device === '-') {
      return false;
    }

    return $db !== $device;
  }

  private function isValidIpv4(string $ip): bool
  {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
  }

  private function isValidNetmask(string $mask): bool
  {
    if (!$this->isValidIpv4($mask)) {
      return false;
    }

    $long = ip2long($mask);
    if ($long === false) {
      return false;
    }

    $u = (int) sprintf('%u', $long);
    $inv = (~$u) & 0xFFFFFFFF;

    return (($inv + 1) & $inv) === 0;
  }

  private function netmaskToPrefix(string $mask): ?int
  {
    if (!$this->isValidNetmask($mask)) {
      return null;
    }

    $u = (int) sprintf('%u', ip2long($mask));
    $bits = 0;

    for ($i = 31; $i >= 0; $i--) {
      if (($u & (1 << $i)) !== 0) {
        $bits++;
      } else {
        break;
      }
    }

    return $bits;
  }

  private function prefixToNetmask(int $prefix): string
  {
    $mask = ($prefix === 0) ? 0 : ((~0 << (32 - $prefix)) & 0xFFFFFFFF);
    return long2ip((int) sprintf('%u', $mask));
  }

  private function setEditNetworkPrefix(string $ip, int $prefix): void
  {
    $this->edit_network = $ip . '/' . $prefix;
  }

  public function updatedEditNetwork($value): void
  {
    if ($this->syncingNetworkMask) {
      return;
    }

    $this->syncingNetworkMask = true;

    $value = trim((string) $value);

    if ($value === '') {
      $this->edit_netmask = '';
      $this->syncingNetworkMask = false;
      return;
    }

    if (str_contains($value, '/')) {
      [$ip, $prefix] = array_pad(explode('/', $value, 2), 2, '');
      $ip = trim($ip);
      $prefix = (int) trim($prefix);

      if ($this->isValidIpv4($ip) && $prefix >= 0 && $prefix <= 32) {
        $this->edit_netmask = $this->prefixToNetmask($prefix);
      } else {
        $this->edit_netmask = '';
      }

      $this->syncingNetworkMask = false;
      return;
    }

    if ($this->isValidIpv4($value)) {
      $prefix = $this->netmaskToPrefix((string) $this->edit_netmask);
      if ($prefix !== null) {
        $this->setEditNetworkPrefix($value, $prefix);
      }
    }

    $this->syncingNetworkMask = false;
  }

  public function updatedEditNetmask($value): void
  {
    if ($this->syncingNetworkMask) {
      return;
    }

    $this->syncingNetworkMask = true;

    $mask = trim((string) $value);
    if ($mask === '') {
      $this->syncingNetworkMask = false;
      return;
    }

    $prefix = $this->netmaskToPrefix($mask);
    if ($prefix === null) {
      $this->syncingNetworkMask = false;
      return;
    }

    $net = trim((string) $this->edit_network);
    if ($net === '') {
      $this->syncingNetworkMask = false;
      return;
    }

    if (str_contains($net, '/')) {
      [$ip] = explode('/', $net, 2);
      $ip = trim($ip);

      if ($this->isValidIpv4($ip)) {
        $this->setEditNetworkPrefix($ip, $prefix);
      }

      $this->syncingNetworkMask = false;
      return;
    }

    if ($this->isValidIpv4($net)) {
      $this->setEditNetworkPrefix($net, $prefix);
    }

    $this->syncingNetworkMask = false;
  }
  public function openEditModal(): void
  {
    $this->guardAuth();

    if (!$this->vlan) {
      return;
    }

    $this->resetValidation();

    $this->edit_site = (string) ($this->vlan->site ?? '');
    $this->edit_name = (string) ($this->vlan->name ?? '');
    $this->edit_vlan_id = $this->vlan->vlan_id !== null ? (string) $this->vlan->vlan_id : '';
    $this->edit_network = (string) ($this->vlan->network ?? '');
    $this->edit_netmask = (string) ($this->vlan->netmask ?? '');
    $this->edit_gateway = (string) ($this->vlan->gateway ?? '');
    $this->edit_remark = (string) ($this->vlan->remark ?? '');

    $this->showEditModal = true;
  }

  public function closeEditModal(): void
  {
    $this->showEditModal = false;
    $this->resetValidation();
  }
  public function saveEdit(): void
  {
    $this->guardAuth();

    if (!$this->vlan) {
      return;
    }

    $validated = $this->validate();

    if ($this->edit_netmask === '' && str_contains((string) $this->edit_network, '/')) {
      [, $prefix] = explode('/', $this->edit_network, 2);
      $prefix = (int) $prefix;

      if ($prefix >= 0 && $prefix <= 32) {
        $this->edit_netmask = $this->prefixToNetmask($prefix);
        $validated['edit_netmask'] = $this->edit_netmask;
      }
    }

    if ($this->edit_netmask !== '' && !$this->isValidNetmask($this->edit_netmask)) {
      $this->addError('edit_netmask', 'Netmask tidak valid.');
      return;
    }

    $this->vlan->update([
      'site' => $validated['edit_site'],
      'name' => $validated['edit_name'],
      'vlan_id' => (int) $validated['edit_vlan_id'],
      'network' => $validated['edit_network'] ?: null,
      'netmask' => $validated['edit_netmask'] ?: null,
      'gateway' => $validated['edit_gateway'] ?: null,
      'remark' => $validated['edit_remark'] ?: null,
    ]);

    $this->showEditModal = false;
    $this->loadData();
  }
  private function normalizeText(?string $value): string
  {
    return preg_replace('/\s+/', ' ', trim((string) $value));
  }

  private function normalizeIp(?string $value): string
  {
    return trim((string) $value);
  }

  public function isVlanNameMismatch(): bool
  {
    if (!$this->vlan || !$this->deviceVlanLoaded) {
      return false;
    }

    $db = $this->normalizeText($this->vlan->name);
    $device = $this->normalizeText($this->deviceInfo['description'] ?? '');

    if ($db === '' || $device === '' || $device === '-') {
      return false;
    }

    return $db !== $device;
  }

  public function isVlanIdMismatch(): bool
  {
    if (!$this->vlan || !$this->deviceVlanLoaded) {
      return false;
    }

    $db = trim((string) $this->vlan->vlan_id);
    $device = trim((string) ($this->deviceInfo['vlan_id'] ?? ''));

    if ($db === '' || $device === '' || $device === '-') {
      return false;
    }

    return $db !== $device;
  }

  public function isGatewayMismatch(): bool
  {
    if (!$this->vlan || !$this->deviceVlanLoaded) {
      return false;
    }

    $db = $this->normalizeIp($this->vlan->gateway);
    $device = $this->normalizeIp($this->deviceInfo['ipv4_address'] ?? '');

    if ($db === '' || $device === '' || $device === '-') {
      return false;
    }

    return $db !== $device;
  }

  public function isSubnetMaskMismatch(): bool
  {
    if (!$this->vlan || !$this->deviceVlanLoaded) {
      return false;
    }

    $db = $this->normalizeIp($this->vlan->netmask);
    $device = $this->normalizeIp($this->deviceInfo['subnet_mask'] ?? '');

    if ($db === '' || $device === '' || $device === '-') {
      return false;
    }

    return $db !== $device;
  }

  public function hasAnyMismatch(): bool
  {
    return $this->isVlanNameMismatch()
      || $this->isVlanIdMismatch()
      || $this->isGatewayMismatch()
      || $this->isSubnetMaskMismatch();
  }
  public function render()
  {
    return view('livewire.network.vlan-view');
  }
}
