<?php

namespace App\Livewire\Device;

use App\Models\AssetSwitch;
use App\Services\NetworkSwitch\Commands\SwitchCommandResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use phpseclib3\Net\SSH2;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('Interface Switch - Network')]
class NetworkSwitchDetail extends Component
{
  private const CACHE_MINUTES = 5;

  #[Locked]
  public int $networkswitchId;

  public ?AssetSwitch $networkSwitch = null;

  public string $routerHost = '';

  public string $switchName = '-';

  public string $switchLocation = '-';

  public string $switchModel = '-';

  public string $switchTypeKey = '';

  public string $switchTypeLabel = 'Belum didukung';

  public string $commandPreview = '';

  public array $interfaces = [];

  public array $interfaceSummary = [
    'total' => 0,
    'up' => 0,
    'down' => 0,
    'disabled' => 0,
  ];

  public string $search = '';

  public string $statusFilter = 'all';

  public bool $isLoading = false;

  public bool $interfacesLoaded = false;

  public bool $fromCache = false;

  public ?string $cachedAt = null;

  public string $interfaceError = '';

  private array $resolvedProfile = [];

  public function mount(int $networkswitchId): void
  {
    $this->guardAuth();

    $this->networkswitchId = $networkswitchId;

    $this->loadSwitch();
    $this->resolveProfile();
  }

  private function guardAuth(): void
  {
    if (!auth()->check()) {
      abort(403, 'Unauthorized.');
    }
  }

  private function loadSwitch(): void
  {
    $user = auth()->user();

    $query = AssetSwitch::query()
      ->with('credential')
      ->whereKey($this->networkswitchId);

    if (!$user->hasRole('administrator')) {
      $sites = $user->sites()
        ->pluck('site')
        ->map(fn($site) => trim((string) $site))
        ->filter()
        ->unique()
        ->values();

      if ($sites->isEmpty()) {
        abort(403, 'Anda tidak memiliki akses ke switch ini.');
      }

      $query->whereIn('location', $sites->all());
    }

    $this->networkSwitch = $query->firstOrFail();

    $this->routerHost = trim((string) (
      $this->networkSwitch->getAttribute('ip_address')
      ?: $this->networkSwitch->getAttribute('hostname')
      ?: ''
    ));

    $this->switchName = $this->firstAttribute([
      'name',
      'device_name',
      'hostname',
    ]);

    $this->switchLocation = $this->firstAttribute([
      'location',
      'site',
    ]);

    $this->switchModel = $this->firstAttribute([
      'model',
      'type',
      'device_type',
    ]);
  }

  private function firstAttribute(array $attributes, string $default = '-'): string
  {
    if (!$this->networkSwitch) {
      return $default;
    }

    foreach ($attributes as $attribute) {
      $value = $this->networkSwitch->getAttribute($attribute);

      if (is_scalar($value) && trim((string) $value) !== '') {
        return trim((string) $value);
      }
    }

    return $default;
  }

  private function resolveProfile(): void
  {
    $this->resolvedProfile = [];
    $this->switchTypeKey = '';
    $this->switchTypeLabel = 'Belum didukung';
    $this->commandPreview = '';

    try {
      $profile = app(SwitchCommandResolver::class)
        ->resolveInterfaceProfile($this->networkSwitch);

      $this->resolvedProfile = $profile;
      $this->switchTypeKey = (string) $profile['key'];
      $this->switchTypeLabel = (string) $profile['label'];
      $this->commandPreview = (string) $profile['command'];
    } catch (\Throwable $e) {
      $this->interfaceError = $e->getMessage();
    }
  }

  public function loadInterfaces(): void
  {
    $this->guardAuth();

    $this->isLoading = true;
    $this->interfaceError = '';
    $this->fromCache = false;

    try {
      $profile = $this->getResolvedProfile();
      $cacheKey = $this->getCacheKey($profile);
      $cached = Cache::get($cacheKey);

      if (is_array($cached)) {
        $this->applyPayload($cached);
        $this->fromCache = true;
        $this->interfacesLoaded = true;

        return;
      }

      $payload = $this->fetchFreshInterfaces($profile);

      Cache::put(
        $cacheKey,
        $payload,
        now()->addMinutes(self::CACHE_MINUTES)
      );

      $this->applyPayload($payload);
      $this->interfacesLoaded = true;
    } catch (\Throwable $e) {
      $this->interfaceError = $e->getMessage();
      $this->interfacesLoaded = false;
    } finally {
      $this->isLoading = false;
    }
  }

  public function refreshInterfaces(): void
  {
    $this->guardAuth();

    try {
      $profile = $this->getResolvedProfile();
      Cache::forget($this->getCacheKey($profile));
    } catch (\Throwable) {
      // Error profile akan ditampilkan kembali oleh loadInterfaces().
    }

    $this->loadInterfaces();
  }

  private function getResolvedProfile(): array
  {
    if ($this->resolvedProfile === []) {
      if (!$this->networkSwitch) {
        $this->loadSwitch();
      }

      $this->resolveProfile();
    }

    if ($this->resolvedProfile === []) {
      throw new \RuntimeException(
        $this->interfaceError !== ''
        ? $this->interfaceError
        : 'Profile command switch tidak ditemukan.'
      );
    }

    return $this->resolvedProfile;
  }

  private function getCacheKey(array $profile): string
  {
    return 'switch_interfaces:'
      . $this->networkswitchId
      . ':'
      . (string) $profile['key']
      . ':'
      . md5($this->routerHost);
  }

  private function fetchFreshInterfaces(array $profile): array
  {
    $ssh = $this->createSshConnection();

    foreach (($profile['disable_paging'] ?? []) as $pagerCommand) {
      try {
        $ssh->exec((string) $pagerCommand);
      } catch (\Throwable) {
        // Tidak menggagalkan pembacaan interface.
      }
    }

    $command = trim((string) ($profile['command'] ?? ''));

    if ($command === '') {
      throw new \RuntimeException('Command interface untuk tipe switch ini kosong.');
    }

    $output = $ssh->exec($command);

    if ($output === false) {
      throw new \RuntimeException('Switch tidak mengembalikan output interface.');
    }

    $output = $this->sanitizeOutput((string) $output);

    if ($output === '') {
      throw new \RuntimeException('Output interface dari switch kosong.');
    }

    if ($this->looksLikeCommandError($output)) {
      throw new \RuntimeException('Command ditolak switch: ' . $output);
    }

    $parserClass = (string) ($profile['parser'] ?? '');

    if ($parserClass === '' || !class_exists($parserClass)) {
      throw new \RuntimeException('Parser interface untuk tipe switch ini tidak tersedia.');
    }

    $parser = app($parserClass);
    $interfaces = $parser->parse($output);

    if ($interfaces === []) {
      throw new \RuntimeException(
        'Output SSH diterima, tetapi interface fisik tidak berhasil dikenali. '
        . 'Jalankan command secara manual dan periksa format output: '
        . $command
      );
    }

    return [
      'interfaces' => $interfaces,
      'summary' => $this->summarize($interfaces),
      'command' => $command,
      'cached_at' => now()->toDateTimeString(),
    ];
  }

  private function createSshConnection(): SSH2
  {
    $this->networkSwitch?->loadMissing('credential');

    $credential = $this->networkSwitch?->credential;

    if (!$credential) {
      throw new \RuntimeException('Credential SSH switch belum dipilih.');
    }

    $host = trim($this->routerHost);
    $username = trim((string) $credential->getAttribute('username'));
    $password = (string) $credential->getAttribute('password');
    $port = (int) ($credential->getAttribute('port') ?: 22);

    if ($host === '') {
      throw new \RuntimeException('IP address atau hostname switch masih kosong.');
    }

    if ($username === '') {
      throw new \RuntimeException('Username credential SSH masih kosong.');
    }

    if ($password === '') {
      throw new \RuntimeException('Password credential SSH masih kosong.');
    }

    if ($port < 1 || $port > 65535) {
      throw new \RuntimeException('Port SSH tidak valid.');
    }

    $ssh = new SSH2($host, $port, 12);
    $ssh->setTimeout(20);

    if (!$ssh->login($username, $password)) {
      throw new \RuntimeException(
        'Login SSH gagal. Periksa IP, port, username, password, ACL, dan konektivitas.'
      );
    }

    return $ssh;
  }

  private function sanitizeOutput(string $output): string
  {
    $output = preg_replace(
      '/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/',
      '',
      $output
    ) ?? $output;

    $output = str_replace(["\r\n", "\r"], "\n", $output);

    $output = preg_replace(
      '/(?:---- More ----|--More--|Press Q to quit|Press any key to continue)/i',
      '',
      $output
    ) ?? $output;

    $output = preg_replace('/\x08+/', '', $output) ?? $output;

    return trim($output);
  }

  private function looksLikeCommandError(string $output): bool
  {
    return preg_match(
      '/(?:unrecognized command|unknown command|invalid input|incomplete command|wrong parameter|ambiguous command|syntax error)/i',
      $output
    ) === 1;
  }

  private function summarize(array $interfaces): array
  {
    $summary = [
      'total' => count($interfaces),
      'up' => 0,
      'down' => 0,
      'disabled' => 0,
    ];

    foreach ($interfaces as $interface) {
      $status = (string) ($interface['status'] ?? 'down');

      if (array_key_exists($status, $summary)) {
        $summary[$status]++;
      } else {
        $summary['down']++;
      }
    }

    return $summary;
  }

  private function applyPayload(array $payload): void
  {
    $this->interfaces = is_array($payload['interfaces'] ?? null)
      ? $payload['interfaces']
      : [];

    $this->interfaceSummary = is_array($payload['summary'] ?? null)
      ? $payload['summary']
      : [
        'total' => 0,
        'up' => 0,
        'down' => 0,
        'disabled' => 0,
      ];

    $this->commandPreview = (string) (
      $payload['command']
      ?? $this->commandPreview
    );

    $this->cachedAt = isset($payload['cached_at'])
      ? (string) $payload['cached_at']
      : null;
  }

  private function filteredInterfaces(): array
  {
    $search = strtolower(trim($this->search));
    $status = $this->statusFilter;

    return array_values(array_filter(
      $this->interfaces,
      static function (array $interface) use ($search, $status): bool {
        if ($status !== 'all' && ($interface['status'] ?? '') !== $status) {
          return false;
        }

        if ($search === '') {
          return true;
        }

        $haystack = strtolower(implode(' ', [
          (string) ($interface['name'] ?? ''),
          (string) ($interface['description'] ?? ''),
          (string) ($interface['speed'] ?? ''),
          (string) ($interface['pvid'] ?? ''),
          (string) ($interface['main_ip'] ?? ''),
        ]));

        return str_contains($haystack, $search);
      }
    ));
  }

  public function render(): View
  {
    return view('livewire.device.network-switch-detail', [
      'filteredInterfaces' => $this->filteredInterfaces(),
    ]);
  }
}
