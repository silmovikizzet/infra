<div wire:init="loadInterfaces">
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
          {{ $routerHost ?: '-' }}
          <span class="mx-1">•</span>
          {{ $switchTypeLabel }}
        </div>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header">
        <h5 class="mb-0">Switch Front Panel</h5>
        <small class="text-muted">
          {{ $switchName }} - {{ $switchModel }}
        </small>
      </div>

      <div class="card-body">
        <div class="switch-panel">
          <div class="switch-panel-header">
            <div class="switch-brand">Switch</div>
            <div class="switch-model">{{ $switchModel }}</div>
          </div>

          <div class="switch-ports-wrap">
            {{-- Port 1-12 --}}
            <div class="port-row">
              @for ($i = 1; $i <= 12; $i++) @php $port=$portMap[$i] ?? null; @endphp @if($port) <div
                class="switch-port {{ $port['type'] }} {{ $port['status'] }}"
                title="Port {{ $port['label'] }} | {{ $port['name'] ?? '-' }} | {{ $port['description'] ?? '-' }} | {{ $port['speed'] ?? '-' }}">
                <span class="port-label">{{ $port['label'] }}</span>
            </div>
            @endif
            @endfor
          </div>

          {{-- Port 13-24 --}}
          <div class="port-row mt-2">
            @for ($i = 13; $i <= 24; $i++) @php $port=$portMap[$i] ?? null; @endphp @if($port) <div
              class="switch-port {{ $port['type'] }} {{ $port['status'] }}"
              title="Port {{ $port['label'] }} | {{ $port['name'] ?? '-' }} | {{ $port['description'] ?? '-' }} | {{ $port['speed'] ?? '-' }}">
              <span class="port-label">{{ $port['label'] }}</span>
          </div>
          @endif
          @endfor
        </div>

        {{-- SFP 25-28 --}}
        <div class="sfp-row mt-4">
          @for ($i = 25; $i <= 28; $i++) @php $port=$portMap[$i] ?? null; @endphp @if($port) <div
            class="switch-port sfp {{ $port['status'] }}"
            title="Port {{ $port['label'] }} | {{ $port['name'] ?? '-' }} | {{ $port['description'] ?? '-' }} | {{ $port['speed'] ?? '-' }}">
            <span class="port-label">{{ $port['label'] }}</span>
        </div>
        @endif
        @endfor
      </div>
    </div>

    <div class="switch-legend mt-4">
      <span class="legend-item"><span class="legend-dot up"></span> Up</span>
      <span class="legend-item"><span class="legend-dot down"></span> Down</span>
      <span class="legend-item"><span class="legend-dot disabled"></span> Disabled</span>
    </div>
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
    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
    Membaca switch...
  </span>
</button>
</div>

@if ($interfaceError !== '')
<div class="alert alert-danger d-flex align-items-start" role="alert">
  <i class="bx bx-error-circle fs-4 me-2 mt-1"></i>
  <div>
    <div class="fw-semibold">Interface switch gagal dimuat</div>
    <div>{{ $interfaceError }}</div>
  </div>
</div>
@endif

@if ($isLoading && ! $interfacesLoaded)
<div class="card mb-6">
  <div class="card-body py-10 text-center">
    <div class="spinner-border text-primary mb-3" role="status"></div>
    <div class="fw-semibold">Mengambil status interface melalui SSH</div>
    <div class="text-muted small mt-1">{{ $commandPreview ?: 'Menentukan command perangkat...' }}</div>
  </div>
</div>
@endif

@if ($interfacesLoaded)
<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
  <span class="badge bg-label-primary">{{ $switchTypeLabel }}</span>

  @if ($fromCache)
  <span class="badge bg-label-warning">Dari cache</span>
  @else
  <span class="badge bg-label-success">Data SSH terbaru</span>
  @endif

  @if ($cachedAt)
  <span class="text-muted small">Diambil {{ $cachedAt }}</span>
  @endif
</div>

<div class="row g-4 mb-6">
  <div class="col-6 col-xl-3">
    <button type="button" class="card h-100 w-100 border-0 text-start {{ $statusFilter === 'all' ? 'shadow-sm' : '' }}"
      wire:click="$set('statusFilter', 'all')">
      <div class="card-body">
        <div class="text-muted small">Semua Interface</div>
        <div class="fs-3 fw-bold mt-1">{{ $interfaceSummary['total'] ?? 0 }}</div>
      </div>
    </button>
  </div>

  <div class="col-6 col-xl-3">
    <button type="button" class="card h-100 w-100 border-0 text-start {{ $statusFilter === 'up' ? 'shadow-sm' : '' }}"
      wire:click="$set('statusFilter', 'up')">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">UP</div>
            <div class="fs-3 fw-bold text-success mt-1">{{ $interfaceSummary['up'] ?? 0 }}</div>
          </div>
          <span class="avatar-initial rounded bg-label-success p-3">
            <i class="bx bx-check-circle fs-4"></i>
          </span>
        </div>
      </div>
    </button>
  </div>

  <div class="col-6 col-xl-3">
    <button type="button" class="card h-100 w-100 border-0 text-start {{ $statusFilter === 'down' ? 'shadow-sm' : '' }}"
      wire:click="$set('statusFilter', 'down')">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Down</div>
            <div class="fs-3 fw-bold text-danger mt-1">{{ $interfaceSummary['down'] ?? 0 }}</div>
          </div>
          <span class="avatar-initial rounded bg-label-danger p-3">
            <i class="bx bx-x-circle fs-4"></i>
          </span>
        </div>
      </div>
    </button>
  </div>

  <div class="col-6 col-xl-3">
    <button type="button"
      class="card h-100 w-100 border-0 text-start {{ $statusFilter === 'disabled' ? 'shadow-sm' : '' }}"
      wire:click="$set('statusFilter', 'disabled')">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div class="text-muted small">Disable</div>
            <div class="fs-3 fw-bold text-secondary mt-1">{{ $interfaceSummary['disabled'] ?? 0 }}</div>
          </div>
          <span class="avatar-initial rounded bg-label-secondary p-3">
            <i class="bx bx-block fs-4"></i>
          </span>
        </div>
      </div>
    </button>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
    <div>
      <h5 class="card-title mb-1">Status Interface</h5>
      <div class="text-muted small">
        Command: <code>{{ $commandPreview }}</code>
      </div>
    </div>

    <div class="d-flex flex-column flex-sm-row gap-2">
      <input type="search" class="form-control" style="min-width: 230px;" placeholder="Cari interface atau deskripsi..."
        wire:model.live.debounce.300ms="search">

      <select class="form-select" style="min-width: 160px;" wire:model.live="statusFilter">
        <option value="all">Semua status</option>
        <option value="up">Up</option>
        <option value="down">Down</option>
        <option value="disabled">Disable</option>
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

        <tr>
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
          <td>{{ $interface['speed'] ?? '-' }}</td>
          <td>{{ $interface['duplex'] ?? '-' }}</td>
          <td>{{ $interface['link_type'] ?? '-' }}</td>
          <td>
            @if ($mode === 'route')
            <code>{{ $interface['main_ip'] ?? '-' }}</code>
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
</div>

<style>
  .switch-panel {
    background: #f4f5f7;
    border: 1px solid #dcdfe4;
    border-radius: 14px;
    padding: 20px;
  }

  .switch-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    font-weight: 600;
  }

  .switch-brand {
    font-size: 1rem;
    color: #444;
  }

  .switch-model {
    font-size: .9rem;
    color: #777;
  }

  .switch-ports-wrap {
    background: #dfe3e8;
    border: 1px solid #cfd5dc;
    border-radius: 12px;
    padding: 16px;
  }

  .port-row,
  .sfp-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .switch-port {
    position: relative;
    width: 48px;
    height: 34px;
    border-radius: 6px;
    border: 1px solid #8f98a3;
    background: #4e5561;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s ease;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .06);
  }

  .switch-port.rj45::before {
    content: '';
    position: absolute;
    bottom: 6px;
    width: 26px;
    height: 10px;
    background: rgba(0, 0, 0, .22);
    border-radius: 2px;
  }

  .switch-port.sfp {
    width: 56px;
    height: 42px;
  }

  .switch-port.up {
    background: #20b15a;
    border-color: #149246;
    box-shadow:
      0 0 10px rgba(32, 177, 90, .45),
      inset 0 0 0 1px rgba(255, 255, 255, .12);
  }

  .switch-port.down {
    background: #555d68;
    border-color: #444b54;
  }

  .switch-port.disabled {
    background: #8a8f98;
    border-color: #727782;
    opacity: .75;
  }

  .port-label {
    position: absolute;
    top: -18px;
    font-size: 11px;
    font-weight: 600;
    color: #555;
  }

  .switch-legend {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
  }

  .legend-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: .9rem;
    color: #555;
  }

  .legend-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: inline-block;
  }

  .legend-dot.up {
    background: #20b15a;
    box-shadow: 0 0 8px rgba(32, 177, 90, .45);
  }

  .legend-dot.down {
    background: #555d68;
  }

  .legend-dot.disabled {
    background: #8a8f98;
  }
</style>
