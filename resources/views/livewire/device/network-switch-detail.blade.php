<div wire:init="loadInterfaces">

  {{-- Header halaman --}}
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-6">
    <div class="d-flex align-items-center gap-3">
      <a href="{{ route('device.switch') }}" class="btn btn-icon btn-light" title="Kembali ke daftar switch">
        <i class="bx bx-arrow-back fs-4"></i>
      </a>

      <div>
        <h4 class="mb-1">{{ $switchName }}</h4>

        <div class="text-muted">
          {{ $switchLocation }}

          <span class="mx-1">•</span>

          <span class="font-monospace">
            {{ $routerHost ?: '-' }}
          </span>

          <span class="mx-1">•</span>

          {{ $switchTypeLabel }}
        </div>
      </div>
    </div>

    <button type="button" class="btn btn-primary" wire:click="refreshInterfaces" wire:loading.attr="disabled"
      wire:target="loadInterfaces,refreshInterfaces">
      <span wire:loading.remove wire:target="loadInterfaces,refreshInterfaces">
        <i class="bx bx-refresh me-1"></i>
        Refresh Interface
      </span>

      <span wire:loading wire:target="loadInterfaces,refreshInterfaces">
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>

        Membaca switch...
      </span>
    </button>
  </div>

  {{-- Error SSH / parser --}}
  @if ($interfaceError !== '')
  <div class="alert alert-danger d-flex align-items-start mb-6" role="alert">
    <i class="bx bx-error-circle fs-4 me-2 mt-1"></i>

    <div>
      <div class="fw-semibold">
        Interface switch gagal dimuat
      </div>

      <div>
        {{ $interfaceError }}
      </div>
    </div>
  </div>
  @endif

  {{-- Loading awal --}}
  @if ($isLoading && ! $interfacesLoaded)
  <div class="card mb-6">
    <div class="card-body py-10 text-center">
      <div class="spinner-border text-primary mb-3" role="status"></div>

      <div class="fw-semibold">
        Mengambil status interface melalui SSH
      </div>

      <div class="text-muted small mt-1">
        {{ $commandPreview ?: 'Menentukan command perangkat...' }}
      </div>
    </div>
  </div>
  @endif

  @if ($interfacesLoaded)

  {{-- Informasi sumber data --}}
  <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <span class="badge bg-label-primary">
      {{ $switchTypeLabel }}
    </span>

    @if ($fromCache)
    <span class="badge bg-label-warning">
      Dari cache
    </span>
    @else
    <span class="badge bg-label-success">
      Data SSH terbaru
    </span>
    @endif

    @if ($cachedAt)
    <span class="text-muted small">
      Diambil {{ $cachedAt }}
    </span>
    @endif
  </div>

  {{-- Visual front panel switch --}}
  <div class="card mb-6">
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
      <div>
        <h5 class="card-title mb-1">
          Switch Front Panel
        </h5>

        <div class="text-muted small">
          {{ $switchName }} — {{ $switchModel }}
        </div>
      </div>

      <div class="switch-legend">
        <span class="legend-item">
          <span class="legend-dot up"></span>
          Up
        </span>

        <span class="legend-item">
          <span class="legend-dot down"></span>
          Down
        </span>

        <span class="legend-item">
          <span class="legend-dot disabled"></span>
          Disable
        </span>
      </div>
    </div>

    <div class="card-body">
      <div class="switch-panel">
        <div class="switch-panel-top">
          <div>
            <div class="switch-brand">
              HPE
            </div>

            <div class="switch-panel-device-name">
              {{ $switchName }}
            </div>
          </div>

          <div class="text-end">
            <div class="switch-panel-model">
              {{ $switchModel }}
            </div>

            <div class="switch-panel-host">
              {{ $routerHost ?: '-' }}
            </div>
          </div>
        </div>

        <div class="switch-front-body">

          {{-- Port RJ45 1 sampai 12 --}}
          <div class="switch-port-section">
            <div class="switch-section-label">
              Port 1–12
            </div>

            <div class="port-row">
              @for ($i = 1; $i <= 12; $i++) @php $port=$portMap[$i] ?? null; @endphp @if ($port) <div
                wire:key="switch-port-{{ $i }}"
                class="switch-port {{ $port['type'] ?? 'rj45' }} {{ $port['status'] ?? 'down' }}"
                title="Port {{ $port['label'] ?? $i }} | {{ $port['name'] ?? '-' }} | {{ $port['description'] ?? '-' }} | {{ $port['speed'] ?? '-' }}">
                <span class="port-number">
                  {{ $port['label'] ?? $i }}
                </span>

                <span class="port-opening"></span>

                <span class="port-led"></span>
            </div>
            @endif
            @endfor
          </div>
        </div>

        {{-- Port RJ45 13 sampai 24 --}}
        <div class="switch-port-section">
          <div class="switch-section-label">
            Port 13–24
          </div>

          <div class="port-row">
            @for ($i = 13; $i <= 24; $i++) @php $port=$portMap[$i] ?? null; @endphp @if ($port) <div
              wire:key="switch-port-{{ $i }}"
              class="switch-port {{ $port['type'] ?? 'rj45' }} {{ $port['status'] ?? 'down' }}"
              title="Port {{ $port['label'] ?? $i }} | {{ $port['name'] ?? '-' }} | {{ $port['description'] ?? '-' }} | {{ $port['speed'] ?? '-' }}">
              <span class="port-number">
                {{ $port['label'] ?? $i }}
              </span>

              <span class="port-opening"></span>

              <span class="port-led"></span>
          </div>
          @endif
          @endfor
        </div>
      </div>

      {{-- Port SFP 25 sampai 28 --}}
      <div class="switch-port-section switch-sfp-section">
        <div class="switch-section-label">
          SFP / SFP+
        </div>

        <div class="sfp-row">
          @for ($i = 25; $i <= 28; $i++) @php $port=$portMap[$i] ?? null; @endphp @if ($port) <div
            wire:key="switch-port-{{ $i }}" class="switch-port sfp {{ $port['status'] ?? 'down' }}"
            title="Port {{ $port['label'] ?? $i }} | {{ $port['name'] ?? '-' }} | {{ $port['description'] ?? '-' }} | {{ $port['speed'] ?? '-' }}">
            <span class="port-number">
              {{ $port['label'] ?? $i }}
            </span>

            <span class="sfp-opening"></span>

            <span class="port-led"></span>
        </div>
        @endif
        @endfor
      </div>
    </div>

  </div>
</div>
</div>
</div>

{{-- Ringkasan interface --}}
<div class="row g-4 mb-6">

  {{-- Semua --}}
  <div class="col-6 col-xl-3">
    <button type="button"
      class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'all' ? 'summary-card-active' : '' }}"
      wire:click="$set('statusFilter', 'all')">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">
              Semua Interface
            </div>

            <div class="fs-3 fw-bold mt-1">
              {{ $interfaceSummary['total'] ?? 0 }}
            </div>
          </div>

          <span class="avatar-initial rounded bg-label-primary p-3">
            <i class="bx bx-network-chart fs-4"></i>
          </span>
        </div>
      </div>
    </button>
  </div>

  {{-- Up --}}
  <div class="col-6 col-xl-3">
    <button type="button"
      class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'up' ? 'summary-card-active' : '' }}"
      wire:click="$set('statusFilter', 'up')">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">
              Up
            </div>

            <div class="fs-3 fw-bold text-success mt-1">
              {{ $interfaceSummary['up'] ?? 0 }}
            </div>
          </div>

          <span class="avatar-initial rounded bg-label-success p-3">
            <i class="bx bx-check-circle fs-4"></i>
          </span>
        </div>
      </div>
    </button>
  </div>

  {{-- Down --}}
  <div class="col-6 col-xl-3">
    <button type="button"
      class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'down' ? 'summary-card-active' : '' }}"
      wire:click="$set('statusFilter', 'down')">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">
              Down
            </div>

            <div class="fs-3 fw-bold text-danger mt-1">
              {{ $interfaceSummary['down'] ?? 0 }}
            </div>
          </div>

          <span class="avatar-initial rounded bg-label-danger p-3">
            <i class="bx bx-x-circle fs-4"></i>
          </span>
        </div>
      </div>
    </button>
  </div>

  {{-- Disabled --}}
  <div class="col-6 col-xl-3">
    <button type="button"
      class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'disabled' ? 'summary-card-active' : '' }}"
      wire:click="$set('statusFilter', 'disabled')">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">
              Disable
            </div>

            <div class="fs-3 fw-bold text-secondary mt-1">
              {{ $interfaceSummary['disabled'] ?? 0 }}
            </div>
          </div>

          <span class="avatar-initial rounded bg-label-secondary p-3">
            <i class="bx bx-block fs-4"></i>
          </span>
        </div>
      </div>
    </button>
  </div>

</div>

{{-- Tabel status interface --}}
<div class="card">
  <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h5 class="card-title mb-1">
        Status Interface
      </h5>

      <div class="text-muted small">
        Command:
        <code>{{ $commandPreview }}</code>
      </div>
    </div>

    <div class="d-flex flex-column flex-sm-row gap-2">
      <div class="input-group">
        <span class="input-group-text">
          <i class="bx bx-search"></i>
        </span>

        <input type="search" class="form-control" style="min-width: 230px;"
          placeholder="Cari interface atau deskripsi..." wire:model.live.debounce.300ms="search">
      </div>

      <select class="form-select" style="min-width: 160px;" wire:model.live="statusFilter">
        <option value="all">
          Semua status
        </option>

        <option value="up">
          Up
        </option>

        <option value="down">
          Down
        </option>

        <option value="disabled">
          Disable
        </option>
      </select>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Interface</th>
          <th>Status</th>
          <th>Mode</th>
          <th>Speed</th>
          <th>Duplex</th>
          <th>Type</th>
          <th>PVID / IP</th>
          <th>Deskripsi</th>
        </tr>
      </thead>

      <tbody>
        @forelse ($filteredInterfaces as $interface)
        @php
        $status = $interface['status'] ?? 'down';

        $statusLabel = match ($status) {
        'up' => 'Up',
        'disabled' => 'Disable',
        default => 'Down',
        };

        $statusClass = match ($status) {
        'up' => 'bg-label-success',
        'disabled' => 'bg-label-secondary',
        default => 'bg-label-danger',
        };

        $mode = $interface['mode'] ?? 'bridge';
        @endphp

        <tr wire:key="interface-row-{{ md5((string) ($interface['name'] ?? uniqid())) }}">
          <td class="fw-semibold text-nowrap">
            {{ $interface['name'] ?? '-' }}
          </td>

          <td>
            <span class="badge {{ $statusClass }}">
              {{ $statusLabel }}
            </span>

            <div class="text-muted small mt-1">
              {{ $interface['raw_status'] ?? '-' }}
            </div>
          </td>

          <td>
            <span class="badge bg-label-info text-uppercase">
              {{ $mode }}
            </span>
          </td>

          <td>
            {{ $interface['speed'] ?? '-' }}
          </td>

          <td>
            {{ $interface['duplex'] ?? '-' }}
          </td>

          <td>
            {{ $interface['link_type'] ?? '-' }}
          </td>

          <td>
            @if ($mode === 'route')
            <code>
                      {{ $interface['main_ip'] ?? '-' }}
                    </code>
            @else
            {{ $interface['pvid'] ?? '-' }}
            @endif
          </td>

          <td style="min-width: 220px;">
            {{ $interface['description'] ?? '-' }}
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="text-center py-8 text-muted">
            Tidak ada interface yang sesuai filter.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endif

{{-- CSS tetap berada di dalam satu root Livewire --}}
<style>
  .switch-panel {
    overflow-x: auto;
    background: #f3f4f6;
    border: 1px solid #d9dde3;
    border-radius: 12px;
    padding: 18px;
  }

  .switch-panel-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    min-width: 920px;
    margin-bottom: 14px;
  }

  .switch-brand {
    font-size: 1rem;
    font-weight: 700;
    color: #303641;
  }

  .switch-panel-device-name {
    margin-top: 2px;
    font-size: .8rem;
    color: #707784;
  }

  .switch-panel-model {
    font-size: .85rem;
    font-weight: 600;
    color: #303641;
  }

  .switch-panel-host {
    margin-top: 2px;
    font-family: monospace;
    font-size: .75rem;
    color: #707784;
  }

  .switch-front-body {
    display: flex;
    align-items: flex-end;
    gap: 18px;
    min-width: 920px;
    padding: 24px 20px 18px;
    background: #f8f9fa;
    border: 1px solid #7f8790;
    border-radius: 4px;
    box-shadow:
      inset 0 1px 0 rgba(255, 255, 255, .9),
      0 2px 4px rgba(0, 0, 0, .06);
  }

  .switch-port-section {
    padding: 10px;
    background: #d7dadd;
    border: 1px solid #9da3aa;
    border-radius: 3px;
  }

  .switch-sfp-section {
    margin-left: auto;
  }

  .switch-section-label {
    margin-bottom: 15px;
    font-size: 10px;
    font-weight: 600;
    color: #59616c;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .port-row,
  .sfp-row {
    display: grid;
    grid-template-columns: repeat(6, 42px);
    gap: 8px;
  }

  .sfp-row {
    grid-template-columns: repeat(2, 50px);
  }

  .switch-port {
    position: relative;
    width: 42px;
    height: 36px;
    background: #545a62;
    border: 2px solid #343940;
    border-radius: 2px;
    transition:
      background .2s ease,
      border-color .2s ease,
      box-shadow .2s ease,
      transform .2s ease;
    cursor: help;
  }

  .switch-port:hover {
    z-index: 2;
    transform: translateY(-2px);
  }

  .switch-port.sfp {
    width: 50px;
    height: 42px;
  }

  .port-number {
    position: absolute;
    top: -17px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 9px;
    font-weight: 700;
    color: #4c535c;
    white-space: nowrap;
  }

  .port-opening {
    position: absolute;
    left: 6px;
    right: 6px;
    top: 8px;
    bottom: 6px;
    background: #252a30;
    border: 1px solid #171a1e;
    border-radius: 1px;
  }

  .port-opening::after {
    content: '';
    position: absolute;
    left: 6px;
    right: 6px;
    bottom: 2px;
    height: 5px;
    background: repeating-linear-gradient(90deg,
        #b7bdc3 0,
        #b7bdc3 2px,
        transparent 2px,
        transparent 4px);
  }

  .sfp-opening {
    position: absolute;
    left: 5px;
    right: 5px;
    top: 8px;
    bottom: 7px;
    background: #252a30;
    border: 1px solid #171a1e;
    border-radius: 1px;
  }

  .port-led {
    position: absolute;
    top: 3px;
    right: 3px;
    width: 5px;
    height: 5px;
    background: #676e76;
    border: 1px solid rgba(0, 0, 0, .35);
    border-radius: 50%;
  }

  .switch-port.up {
    background: #2fc463;
    border-color: #168d42;
    box-shadow:
      0 0 9px rgba(47, 196, 99, .45),
      inset 0 0 0 1px rgba(255, 255, 255, .18);
  }

  .switch-port.up .port-led {
    background: #d9ff5a;
    border-color: #a0bf21;
    box-shadow: 0 0 7px rgba(217, 255, 90, .9);
  }

  .switch-port.down {
    background: #555b63;
    border-color: #373c43;
  }

  .switch-port.disabled {
    background: #a2a6ac;
    border-color: #7c8188;
    opacity: .65;
  }

  .switch-port.disabled .port-opening,
  .switch-port.disabled .sfp-opening {
    background: #666b72;
  }

  .switch-legend {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
  }

  .legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .8rem;
    color: #69707a;
  }

  .legend-dot {
    display: inline-block;
    width: 11px;
    height: 11px;
    border-radius: 50%;
  }

  .legend-dot.up {
    background: #2fc463;
    box-shadow: 0 0 6px rgba(47, 196, 99, .55);
  }

  .legend-dot.down {
    background: #555b63;
  }

  .legend-dot.disabled {
    background: #a2a6ac;
  }

  .summary-card {
    color: inherit;
    transition:
      transform .2s ease,
      box-shadow .2s ease;
  }

  .summary-card:hover,
  .summary-card-active {
    transform: translateY(-2px);
    box-shadow: 0 .25rem 1rem rgba(34, 48, 62, .12);
  }

  @media (max-width: 767.98px) {
    .switch-panel {
      padding: 12px;
    }
  }
</style>

</div>
