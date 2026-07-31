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

    /**
     * Front panel hasil pengelompokan interface fisik.
     *
     * Setiap member stack memiliki satu chassis sendiri. Contoh:
     * XGE1/0/1 -> member 1, slot 0, port 1
     * XGE2/0/1 -> member 2, slot 0, port 1
     */
    public array $switchMembers = [];
    public bool $isStacked = false;
    public int $stackMemberCount = 0;

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
        if (! auth()->check()) {
            abort(403, 'Unauthorized.');
        }
    }

    private function isAdministrator(): bool
    {
        $user = auth()->user();

        return $user !== null
            && in_array(
                strtolower(trim((string) $user->role)),
                ['admin', 'administrator'],
                true
            );
    }

    private function loadSwitch(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        $query = AssetSwitch::query()
            ->with('credential')
            ->whereKey($this->networkswitchId);

        if (! $this->isAdministrator()) {
            $sites = $user->sites()
                ->pluck('site')
                ->map(fn ($site) => trim((string) $site))
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
        if (! $this->networkSwitch) {
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
            $this->switchTypeKey = (string) ($profile['key'] ?? '');
            $this->switchTypeLabel = (string) ($profile['label'] ?? 'Belum didukung');
            $this->commandPreview = (string) ($profile['command'] ?? '');
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
            $this->interfaces = [];
            $this->switchMembers = [];
            $this->stackMemberCount = 0;
            $this->isStacked = false;
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
            // Profile error akan ditampilkan kembali oleh loadInterfaces().
        }

        $this->loadInterfaces();
    }

    private function getResolvedProfile(): array
    {
        if ($this->resolvedProfile === []) {
            if (! $this->networkSwitch) {
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
        return 'switch_interfaces:v2:'
            . $this->networkswitchId
            . ':'
            . (string) ($profile['key'] ?? 'unknown')
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
                // Kegagalan disable paging tidak menggagalkan pembacaan interface.
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

        if ($parserClass === '' || ! class_exists($parserClass)) {
            throw new \RuntimeException('Parser interface untuk tipe switch ini tidak tersedia.');
        }

        $parser = app($parserClass);
        $interfaces = $parser->parse($output);

        if (! is_array($interfaces) || $interfaces === []) {
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

        if (! $credential) {
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

        if (! $ssh->login($username, $password)) {
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
            $status = $this->normalizeStatus((string) ($interface['status'] ?? 'down'));

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

        // Wajib dipanggil juga untuk payload dari cache.
        $this->buildSwitchMembers();
    }

    /**
     * Mengubah daftar interface menjadi chassis/member stack.
     * Urutan fisik: 1 di atas, 2 di bawah, 3 di atas kanan, 4 di bawah kanan, dst.
     */
    private function buildSwitchMembers(): void
    {
        $members = [];

        foreach ($this->interfaces as $interface) {
            if (! is_array($interface)) {
                continue;
            }

            $name = trim((string) ($interface['name'] ?? ''));
            $parsed = $this->parsePhysicalInterfaceName($name);

            if ($parsed === null) {
                continue;
            }

            $member = $parsed['member'];
            $slot = $parsed['slot'];
            $portNumber = $parsed['port'];
            $subPort = $parsed['sub_port'];
            $media = $this->resolvePortMedia($parsed['prefix']);
            $status = $this->normalizeStatus((string) ($interface['status'] ?? 'down'));

            $members[$member] ??= [
                'member' => $member,
                'label' => 'Member ' . $member,
                'port_count' => 0,
                'up_count' => 0,
                'down_count' => 0,
                'disabled_count' => 0,
                'slots' => [],
            ];

            $members[$member]['slots'][$slot] ??= [
                'slot' => $slot,
                'label' => 'Slot ' . $slot,
                'pair_columns' => 1,
                'ports' => [],
            ];

            $displayNumber = $subPort !== null
                ? $portNumber . ':' . $subPort
                : (string) $portNumber;

            $port = [
                'key' => $member . '-' . $slot . '-' . $portNumber . '-' . ($subPort ?? 0),
                'member' => $member,
                'slot' => $slot,
                'port_number' => $portNumber,
                'sub_port' => $subPort,
                'display_number' => $displayNumber,
                'interface_name' => $name,
                'prefix' => $parsed['prefix'],
                'status' => $status,
                'raw_status' => (string) ($interface['raw_status'] ?? '-'),
                'description' => trim((string) ($interface['description'] ?? '')),
                'speed' => trim((string) ($interface['speed'] ?? '')),
                'duplex' => trim((string) ($interface['duplex'] ?? '')),
                'mode' => trim((string) ($interface['mode'] ?? '')),
                'pvid' => trim((string) ($interface['pvid'] ?? '')),
                'main_ip' => trim((string) ($interface['main_ip'] ?? '')),
                'media_key' => $media['key'],
                'media_label' => $media['label'],
                'media_class' => $media['class'],
                'grid_column' => intdiv($portNumber - 1, 2) + 1,
                'grid_row' => $portNumber % 2 === 1 ? 1 : 2,
            ];

            $members[$member]['slots'][$slot]['ports'][] = $port;
            $members[$member]['port_count']++;
            $members[$member][$status . '_count']++;
        }

        ksort($members, SORT_NUMERIC);

        foreach ($members as &$memberData) {
            ksort($memberData['slots'], SORT_NUMERIC);

            foreach ($memberData['slots'] as &$slotData) {
                usort(
                    $slotData['ports'],
                    static function (array $left, array $right): int {
                        $portCompare = $left['port_number'] <=> $right['port_number'];

                        if ($portCompare !== 0) {
                            return $portCompare;
                        }

                        return ($left['sub_port'] ?? 0) <=> ($right['sub_port'] ?? 0);
                    }
                );

                $maxPort = 1;

                foreach ($slotData['ports'] as $port) {
                    $maxPort = max($maxPort, (int) $port['port_number']);
                }

                $slotData['pair_columns'] = max(1, (int) ceil($maxPort / 2));
                $slotData['ports'] = array_values($slotData['ports']);
            }
            unset($slotData);

            $memberData['slots'] = array_values($memberData['slots']);
        }
        unset($memberData);

        $this->switchMembers = array_values($members);
        $this->stackMemberCount = count($this->switchMembers);
        $this->isStacked = $this->stackMemberCount > 1;
    }

    /**
     * Mendukung bentuk singkat dan panjang, misalnya:
     * GE1/0/1
     * XGE2/0/1
     * GigabitEthernet1/0/1
     * Ten-GigabitEthernet1/0/1
     * FortyGigE1/0/49
     * HundredGigE2/0/49
     */
    private function parsePhysicalInterfaceName(string $interfaceName): ?array
    {
        $compact = preg_replace('/\s+/', '', trim($interfaceName)) ?? '';

        if ($compact === '') {
            return null;
        }

        if (! preg_match(
            '/^(.+?)(\d+)\/(\d+)\/(\d+)(?::(\d+))?$/i',
            $compact,
            $matches
        )) {
            return null;
        }

        $prefix = trim((string) $matches[1]);
        $normalizedPrefix = strtolower(
            preg_replace('/[^a-z0-9]/i', '', $prefix) ?? ''
        );

        if (! $this->isSupportedPhysicalPrefix($normalizedPrefix)) {
            return null;
        }

        return [
            'prefix' => $normalizedPrefix,
            'member' => max(1, (int) $matches[2]),
            'slot' => max(0, (int) $matches[3]),
            'port' => max(1, (int) $matches[4]),
            'sub_port' => isset($matches[5]) && $matches[5] !== ''
                ? max(1, (int) $matches[5])
                : null,
        ];
    }

    private function isSupportedPhysicalPrefix(string $prefix): bool
    {
        return in_array($prefix, [
            'ge',
            'gigabitethernet',
            'mge',
            'multigigabitethernet',
            'xge',
            '10ge',
            'tenge',
            'tengigabitethernet',
            'sfp',
            'sfpplus',
            '25ge',
            'twentyfivegige',
            'twentyfivegigabitethernet',
            'sfp28',
            'fge',
            '40ge',
            'fortygige',
            'fortygigabitethernet',
            'qsfp',
            'qsfpplus',
            '50ge',
            'fiftygige',
            'fiftygigabitethernet',
            'hge',
            '100ge',
            'hundredgige',
            'hundredgigabitethernet',
            'qsfp28',
        ], true);
    }

    /**
     * GE = RJ45/UTP.
     * XGE/10GE = SFP/SFP+.
     * 25GE = SFP28.
     * FGE/40GE = QSFP+.
     * 50GE = QSFP28.
     * HGE/100GE = QSFP28.
     */
    private function resolvePortMedia(string $prefix): array
    {
        if (in_array($prefix, [
            'ge',
            'gigabitethernet',
            'mge',
            'multigigabitethernet',
        ], true)) {
            return [
                'key' => 'rj45',
                'label' => 'GE / UTP',
                'class' => 'port-rj45',
            ];
        }

        if (in_array($prefix, [
            '25ge',
            'twentyfivegige',
            'twentyfivegigabitethernet',
            'sfp28',
        ], true)) {
            return [
                'key' => 'sfp28',
                'label' => '25GE / SFP28',
                'class' => 'port-sfp28',
            ];
        }

        if (in_array($prefix, [
            'fge',
            '40ge',
            'fortygige',
            'fortygigabitethernet',
            'qsfp',
            'qsfpplus',
        ], true)) {
            return [
                'key' => 'qsfp',
                'label' => '40GE / QSFP+',
                'class' => 'port-qsfp',
            ];
        }

        if (in_array($prefix, [
            '50ge',
            'fiftygige',
            'fiftygigabitethernet',
        ], true)) {
            return [
                'key' => 'qsfp28',
                'label' => '50GE / QSFP28',
                'class' => 'port-qsfp28',
            ];
        }

        if (in_array($prefix, [
            'hge',
            '100ge',
            'hundredgige',
            'hundredgigabitethernet',
            'qsfp28',
        ], true)) {
            return [
                'key' => 'qsfp28',
                'label' => '100GE / QSFP28',
                'class' => 'port-qsfp28',
            ];
        }

        return [
            'key' => 'sfp',
            'label' => 'XGE / SFP+',
            'class' => 'port-sfp',
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'up', 'link-up', 'connected' => 'up',
            'disabled', 'disable', 'adm', 'admin-down', 'administratively-down' => 'disabled',
            default => 'down',
        };
    }

    private function filteredInterfaces(): array
    {
        $search = strtolower(trim($this->search));
        $status = $this->statusFilter;

        return array_values(array_filter(
            $this->interfaces,
            function (array $interface) use ($search, $status): bool {
                $normalizedStatus = $this->normalizeStatus(
                    (string) ($interface['status'] ?? 'down')
                );

                if ($status !== 'all' && $normalizedStatus !== $status) {
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
