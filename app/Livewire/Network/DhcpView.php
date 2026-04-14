<?php

namespace App\Livewire\Network;

use App\Models\AssetSwitch;
use App\Models\Credential;
use App\Models\DhcpPool;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use phpseclib3\Net\SSH2;
use Illuminate\Support\Facades\Cache;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('DHCP Pool Detail - Network')]
class DhcpView extends Component
{
  public int $dhcpPoolIdParam;

  public ?DhcpPool $dhcpPool = null;
  public ?AssetSwitch $switch = null;
  public ?Credential $credential = null;

  public string $sshOutput = '';
  public string $sshError = '';
  public bool $isLoadingRemote = false;
  public bool $remoteLoaded = false;

  public string $routerHost = '';
  public string $commandPreview = '';
  public string $selectedCommand = 'dhcp_stats_pool';
  public array $commandOptions = [];
  public array $deviceDhcpInfo = [];
  public bool $isLoadingDeviceDhcp = false;
  public bool $deviceDhcpLoaded = false;
  public string $deviceDhcpError = '';
  public bool $deviceDhcpFromCache = false;
  public ?string $deviceDhcpCachedAt = null;
  public bool $showEditModal = false;

  public string $edit_site = '';
  public string $edit_name = '';
  public string $edit_network = '';
  public string $edit_netmask = '';
  public array $edit_gateway_list = [];
  public array $edit_dns_list = [];
  public ?string $edit_lease_seconds = null;
  public array $edit_options = [];
  public string $edit_remark = '';

  // helper textarea/input sementara
  public string $edit_gateway_text = '';
  public string $edit_dns_text = '';
  public string $edit_options_text = '';
  public array $siteOptions = [];
  public bool $syncingNetworkMask = false;
  public array $fieldDiffs = [];
  public function mount(int $dhcpPoolId): void
  {
    $this->guardAuth();
    $this->dhcpPoolIdParam = $dhcpPoolId;

    $this->loadSiteOptions();
    $this->loadData();
    $this->loadDeviceDhcpPool();
  }
  protected function rules(): array
  {
    return [
      'edit_site' => ['nullable', 'string', 'max:255'],
      'edit_name' => ['required', 'string', 'max:255'],
      'edit_network' => [
        'nullable',
        'regex:/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\/([0-9]|[1-2][0-9]|3[0-2]))?$/'
      ],
      'edit_netmask' => ['nullable', 'ip'],
      'edit_lease_seconds' => ['nullable', 'integer', 'min:0'],
      'edit_remark' => ['nullable', 'string'],
    ];
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
  private function parseLineOrCommaList(?string $value): array
  {
    $value = trim((string) $value);

    if ($value === '') {
      return [];
    }

    $parts = preg_split('/[\r\n,;]+/', $value) ?: [];

    return array_values(array_filter(array_map(
      fn($item) => trim((string) $item),
      $parts
    ), fn($item) => $item !== ''));
  }
  private function parseOptionsText(?string $value): array
  {
    $value = trim((string) $value);

    if ($value === '') {
      return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
    $result = [];

    foreach ($lines as $line) {
      $line = trim($line);

      if ($line === '') {
        continue;
      }

      if (preg_match('/^\s*option\s+(\d+)\s+(.+)$/i', $line, $m)) {
        $code = (int) ($m[1] ?? 0);
        $rawValue = trim((string) ($m[2] ?? ''));

        if ($code > 0 && $rawValue !== '') {
          $result[] = [
            'code' => $code,
            'value' => $rawValue,
          ];
        }
      }
    }

    return $result;
  }
  private function formatOptionsText(array $options): string
  {
    $lines = [];

    foreach ($options as $opt) {
      $code = trim((string) ($opt['code'] ?? ''));
      $value = trim((string) ($opt['value'] ?? ''));

      if ($code !== '' && $value !== '') {
        $lines[] = 'option ' . $code . ' ' . $value;
      }
    }

    return implode("\n", $lines);
  }
  public function openEditModal(): void
  {
    $this->guardAuth();

    if (!$this->dhcpPool) {
      return;
    }

    $this->resetValidation();

    $this->edit_site = (string) ($this->dhcpPool->site ?? '');
    $this->edit_name = (string) ($this->dhcpPool->name ?? '');
    $this->edit_network = (string) ($this->dhcpPool->network ?? '');
    $this->edit_netmask = (string) ($this->dhcpPool->netmask ?? '');
    $this->edit_lease_seconds = $this->dhcpPool->lease_seconds !== null
      ? (string) $this->dhcpPool->lease_seconds
      : null;
    $this->edit_remark = (string) ($this->dhcpPool->remark ?? '');

    $gatewayList = is_array($this->dhcpPool->gateway_list) ? $this->dhcpPool->gateway_list : [];
    $dnsList = is_array($this->dhcpPool->dns_list) ? $this->dhcpPool->dns_list : [];
    $options = is_array($this->dhcpPool->options) ? $this->dhcpPool->options : [];

    $this->edit_gateway_text = implode(", ", $gatewayList);
    $this->edit_dns_text = implode(", ", $dnsList);
    $this->edit_options_text = $this->formatOptionsText($options);

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

    if (!$this->dhcpPool) {
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

    $gatewayList = $this->parseLineOrCommaList($this->edit_gateway_text);
    $dnsList = $this->parseLineOrCommaList($this->edit_dns_text);
    $options = $this->parseOptionsText($this->edit_options_text);

    $this->dhcpPool->update([
      'site' => $validated['edit_site'] ?: null,
      'name' => $validated['edit_name'],
      'network' => $validated['edit_network'] ?: null,
      'netmask' => $validated['edit_netmask'] ?: null,
      'gateway_list' => $gatewayList,
      'dns_list' => $dnsList,
      'lease_seconds' => $validated['edit_lease_seconds'] !== null && $validated['edit_lease_seconds'] !== ''
        ? (int) $validated['edit_lease_seconds']
        : null,
      'options' => $options,
      'remark' => $validated['edit_remark'] ?: null,
    ]);

    $this->showEditModal = false;
    $this->loadData();
  }
  private function guardAuth(): void
  {
    if (!auth()->check()) {
      abort(403, 'Unauthorized.');
    }
  }

  private function loadData(): void
  {
    $this->dhcpPool = DhcpPool::query()
      ->with('vlans:id,dhcp_pool_id,name,vlan_id,site,network,gateway')
      ->findOrFail($this->dhcpPoolIdParam);

    $this->switch = AssetSwitch::query()
      ->with('credential')
      ->where('group', 'core')
      ->where('location', trim((string) $this->dhcpPool->site))
      ->whereNotNull('credential_id')
      ->first();

    $this->credential = $this->switch?->credential;

    $this->routerHost = (string) (
      $this->switch?->ip_address
      ?? $this->switch?->hostname
      ?? ''
    );

    $poolName = trim((string) ($this->dhcpPool->name ?? ''));

    $this->commandOptions = [
      'dhcp_stats_pool' => [
        'label' => 'Lihat statistik DHCP pool',
        'command' => 'display dhcp server statistics pool ' . $poolName,
      ],
    ];

    if (!array_key_exists($this->selectedCommand, $this->commandOptions)) {
      $this->selectedCommand = array_key_first($this->commandOptions) ?? '';
    }

    $this->commandPreview = $this->commandOptions[$this->selectedCommand]['command'] ?? '';
  }
  private function getDisplayDhcpPoolCommand(): string
  {
    $poolName = trim((string) ($this->dhcpPool?->name ?? ''));

    if ($poolName === '') {
      return '';
    }

    return 'display dhcp server pool ' . $poolName;
  }
  private function fetchDeviceDhcpPoolFresh(): array
  {
    $command = $this->getDisplayDhcpPoolCommand();

    if ($command === '') {
      throw new \RuntimeException('Nama DHCP pool kosong.');
    }

    $output = $this->execSshCommand($command);

    return $this->parseDisplayDhcpPoolOutput($output);
  }

  private function getDeviceDhcpCacheKey(): string
  {
    return 'dhcp_pool_device_detail:' . (int) $this->dhcpPoolIdParam . ':switch:' . md5((string) $this->routerHost);
  }
  private function execSshCommand(string $command): string
  {
    if (!$this->dhcpPool) {
      throw new \RuntimeException('Data DHCP pool tidak ditemukan.');
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

    if (trim($command) === '') {
      throw new \RuntimeException('Command kosong.');
    }

    $ssh = new SSH2($host, $port, 10);

    if (!$ssh->login($username, $password)) {
      throw new \RuntimeException('Login SSH gagal. Periksa host, username, password, atau port.');
    }

    $output = $ssh->exec($command);

    if ($output === false || trim((string) $output) === '') {
      $stderr = method_exists($ssh, 'getStdError')
        ? trim((string) $ssh->getStdError())
        : '';

      throw new \RuntimeException(
        $stderr !== '' ? $stderr : 'Switch tidak mengembalikan output.'
      );
    }

    return trim((string) $output);
  }
  private function parseDisplayDhcpPoolOutput(string $output): array
  {
    $text = trim($output);

    $result = [
      'pool_name' => (string) ($this->dhcpPool->name ?? '-'),
      'network' => '-',
      'mask' => '-',
      'gateway_list' => [],
      'dns_list' => [],
      'lease' => '-',
      'options' => [],
      'description' => '-',
      'forbidden_ip_list' => [],
    ];

    if (preg_match('/^\s*Pool\s+name\s*:\s*(.+?)\s*$/im', $text, $m)) {
      $result['pool_name'] = trim($m[1]);
    }

    // Network + mask dalam satu baris:
    // Network: 10.2.16.0 mask 255.255.254.0
    if (
      preg_match(
        '/^\s*Network\s*:\s*([0-9]{1,3}(?:\.[0-9]{1,3}){3})\s+mask\s+([0-9]{1,3}(?:\.[0-9]{1,3}){3})\s*$/im',
        $text,
        $m
      )
    ) {
      $result['network'] = trim($m[1]);
      $result['mask'] = trim($m[2]);
    }

    // dns-list 10.2.0.251 172.16.0.252
    if (preg_match('/^\s*dns-list\s+(.+?)\s*$/im', $text, $m)) {
      $result['dns_list'] = $this->splitIpList($m[1]);
    }

    // gateway-list 10.2.17.254
    if (preg_match('/^\s*gateway-list\s+(.+?)\s*$/im', $text, $m)) {
      $result['gateway_list'] = $this->splitIpList($m[1]);
    }

    // expired 30 0 0 0
    if (preg_match('/^\s*expired\s+(.+?)\s*$/im', $text, $m)) {
      $result['lease'] = trim($m[1]);
    }

    // forbidden-ip bisa muncul banyak kali
    if (preg_match_all('/^\s*forbidden-ip\s+([0-9]{1,3}(?:\.[0-9]{1,3}){3})\s*$/im', $text, $matches)) {
      $result['forbidden_ip_list'] = array_values(array_unique($matches[1]));
    }

    if (preg_match_all('/^\s*option\s+(\d+)\s+(.+?)\s*$/im', $text, $matches, PREG_SET_ORDER)) {
      $options = [];

      foreach ($matches as $row) {
        $code = (int) $row[1];
        $rawValue = trim($row[2]);

        $options[] = [
          'code' => $code,
          'value' => $rawValue,
        ];
      }

      $result['options'] = $options;
    }

    return $result;
  }
  private function splitIpList(?string $value): array
  {
    $value = trim((string) $value);

    if ($value === '' || $value === '-') {
      return [];
    }

    $items = preg_split('/\s*,\s*|\s*;\s*|\s+/', $value) ?: [];

    return array_values(array_filter(array_map('trim', $items), fn($item) => $item !== ''));
  }
  public function loadDeviceDhcpPool(): void
  {
    $this->guardAuth();

    $this->deviceDhcpError = '';
    $this->deviceDhcpLoaded = false;
    $this->isLoadingDeviceDhcp = true;
    $this->deviceDhcpFromCache = false;

    try {
      $cacheKey = $this->getDeviceDhcpCacheKey();
      $cached = Cache::get($cacheKey);

      if (is_array($cached)) {
        $this->deviceDhcpInfo = $cached['data'] ?? [];
        $this->deviceDhcpCachedAt = $cached['cached_at'] ?? null;
        $this->deviceDhcpFromCache = true;
      } else {
        $fresh = $this->fetchDeviceDhcpPoolFresh();

        $payload = [
          'data' => $fresh,
          'cached_at' => now()->toDateTimeString(),
        ];

        Cache::put($cacheKey, $payload, now()->addHour());

        $this->deviceDhcpInfo = $payload['data'];
        $this->deviceDhcpCachedAt = $payload['cached_at'];
        $this->deviceDhcpFromCache = false;
      }

      $this->deviceDhcpLoaded = true;
    } catch (\Throwable $e) {
      $this->deviceDhcpError = $e->getMessage();
    } finally {
      $this->isLoadingDeviceDhcp = false;
    }
  }
  public function refreshDeviceDhcpPool(): void
  {
    $this->guardAuth();

    Cache::forget($this->getDeviceDhcpCacheKey());
    $this->loadDeviceDhcpPool();
  }
  private function getSelectedCommand(): string
  {
    return $this->commandOptions[$this->selectedCommand]['command'] ?? '';
  }

  public function updatedSelectedCommand(string $value): void
  {
    $this->commandPreview = $this->commandOptions[$value]['command'] ?? '';
  }

  public function refreshRemote(): void
  {
    $this->guardAuth();

    $this->sshOutput = '';
    $this->sshError = '';
    $this->remoteLoaded = false;
    $this->isLoadingRemote = true;

    try {
      if (!$this->dhcpPool) {
        throw new \RuntimeException('Data DHCP pool tidak ditemukan.');
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

      $command = trim($this->getSelectedCommand());

      if ($command === '') {
        throw new \RuntimeException('Command belum dipilih.');
      }

      $ssh = new SSH2($host, $port, 10);

      if (!$ssh->login($username, $password)) {
        throw new \RuntimeException('Login SSH gagal. Periksa host, username, password, atau port.');
      }

      $output = $ssh->exec($command);

      if ($output === false || trim((string) $output) === '') {
        $stderr = method_exists($ssh, 'getStdError')
          ? trim((string) $ssh->getStdError())
          : '';

        throw new \RuntimeException(
          $stderr !== '' ? $stderr : 'Switch tidak mengembalikan output.'
        );
      }

      $this->sshOutput = trim((string) $output);
      $this->remoteLoaded = true;
    } catch (\Throwable $e) {
      $this->sshError = $e->getMessage();
    } finally {
      $this->isLoadingRemote = false;
    }
  }
  private function normalizeText(?string $value): string
  {
    return trim((string) $value);
  }

  private function normalizeIpArray(array|string|null $value): array
  {
    $items = is_array($value) ? $value : $this->splitIpList((string) $value);

    $out = [];
    foreach ($items as $item) {
      $item = trim((string) $item);
      if ($item === '') {
        continue;
      }
      $out[] = $item;
    }

    $out = array_values(array_unique($out));
    sort($out);

    return $out;
  }

  private function normalizeOptions(array $options): array
  {
    $out = [];

    foreach ($options as $opt) {
      $code = (int) ($opt['code'] ?? 0);
      $value = preg_replace('/\s+/', ' ', trim((string) ($opt['value'] ?? '')));

      if ($code <= 0 || $value === '') {
        continue;
      }

      $out[] = [
        'code' => $code,
        'value' => $value,
      ];
    }

    usort($out, function ($a, $b) {
      return [$a['code'], $a['value']] <=> [$b['code'], $b['value']];
    });

    return $out;
  }

  private function parseDeviceLeaseToSeconds(?string $value): ?int
  {
    $value = trim((string) $value);

    if ($value === '' || $value === '-') {
      return null;
    }

    $parts = preg_split('/\s+/', $value) ?: [];
    $parts = array_values(array_filter($parts, fn($v) => $v !== ''));

    if (count($parts) !== 4) {
      return null;
    }

    $days = (int) $parts[0];
    $hours = (int) $parts[1];
    $minutes = (int) $parts[2];
    $seconds = (int) $parts[3];

    return ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
  }

  public function isPoolNameMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    return $this->normalizeText($this->dhcpPool->name) !==
      $this->normalizeText($this->deviceDhcpInfo['pool_name'] ?? '');
  }
  private function normalizeNetworkBase(?string $value): string
  {
    $value = trim((string) $value);

    if ($value === '') {
      return '';
    }

    if (str_contains($value, '/')) {
      [$ip] = explode('/', $value, 2);
      return trim($ip);
    }

    return $value;
  }
  public function isNetworkMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    $dbNetwork = $this->normalizeNetworkBase($this->dhcpPool->network);
    $deviceNetwork = $this->normalizeNetworkBase($this->deviceDhcpInfo['network'] ?? '');

    return $dbNetwork !== $deviceNetwork;
  }

  public function isNetmaskMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    return $this->normalizeText($this->dhcpPool->netmask) !==
      $this->normalizeText($this->deviceDhcpInfo['mask'] ?? '');
  }

  public function isGatewayMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    $db = $this->normalizeIpArray(is_array($this->dhcpPool->gateway_list) ? $this->dhcpPool->gateway_list : []);
    $device = $this->normalizeIpArray($this->deviceDhcpInfo['gateway_list'] ?? []);

    return $db !== $device;
  }

  public function isDnsMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    $db = $this->normalizeIpArray(is_array($this->dhcpPool->dns_list) ? $this->dhcpPool->dns_list : []);
    $device = $this->normalizeIpArray($this->deviceDhcpInfo['dns_list'] ?? []);

    return $db !== $device;
  }

  public function isLeaseMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    $db = (int) ($this->dhcpPool->lease_seconds ?? 0);
    $device = $this->parseDeviceLeaseToSeconds($this->deviceDhcpInfo['lease'] ?? '');

    if ($device === null) {
      return false;
    }

    return $db !== $device;
  }

  public function isOptionsMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    $db = $this->normalizeOptions(is_array($this->dhcpPool->options) ? $this->dhcpPool->options : []);
    $device = $this->normalizeOptions($this->deviceDhcpInfo['options'] ?? []);

    return $db !== $device;
  }

  public function isRemarkMismatch(): bool
  {
    if (!$this->dhcpPool || !$this->deviceDhcpLoaded) {
      return false;
    }

    $deviceRemark = $this->normalizeText($this->deviceDhcpInfo['description'] ?? '');

    if ($deviceRemark === '' || $deviceRemark === '-') {
      return false;
    }

    return $this->normalizeText($this->dhcpPool->remark) !== $deviceRemark;
  }
  public function hasAnyMismatch(): bool
  {
    return $this->isPoolNameMismatch()
      || $this->isNetworkMismatch()
      || $this->isNetmaskMismatch()
      || $this->isGatewayMismatch()
      || $this->isDnsMismatch()
      || $this->isLeaseMismatch()
      || $this->isOptionsMismatch()
      || $this->isRemarkMismatch();
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

    // format IP/prefix
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

    // format IP saja, kalau netmask valid maka ubah jadi CIDR
    $ip = $value;
    if ($this->isValidIpv4($ip)) {
      $prefix = $this->netmaskToPrefix((string) $this->edit_netmask);
      if ($prefix !== null) {
        $this->setEditNetworkPrefix($ip, $prefix);
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

    // kalau network sudah CIDR, ganti prefix-nya
    if (str_contains($net, '/')) {
      [$ip] = explode('/', $net, 2);
      $ip = trim($ip);

      if ($this->isValidIpv4($ip)) {
        $this->setEditNetworkPrefix($ip, $prefix);
      }

      $this->syncingNetworkMask = false;
      return;
    }

    // kalau network hanya IP biasa
    if ($this->isValidIpv4($net)) {
      $this->setEditNetworkPrefix($net, $prefix);
    }

    $this->syncingNetworkMask = false;
  }
  public function render()
  {
    return view('livewire.network.dhcp-view');
  }
}
