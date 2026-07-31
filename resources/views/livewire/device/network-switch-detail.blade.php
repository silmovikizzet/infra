<div wire:init="loadInterfaces">
  {{-- Header --}}
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-6">
    <div class="d-flex align-items-center gap-3">
      <a href="{{ route('device.switch') }}" class="btn btn-icon btn-light" title="Kembali ke daftar switch">
        <i class="bx bx-arrow-back fs-4"></i>
      </a>

      <div>
        <h4 class="mb-1">{{ $switchName }}</h4>
        <div class="text-muted d-flex flex-wrap align-items-center gap-2">
          <span>{{ $switchLocation }}</span>
          <span>•</span>
          <span class="font-monospace">{{ $routerHost ?: '-' }}</span>
          <span>•</span>
          <span>{{ $switchTypeLabel }}</span>
        </div>
      </div>
    </div>

    <button
      type="button"
      class="btn btn-primary"
      wire:click="refreshInterfaces"
      wire:loading.attr="disabled"
      wire:target="loadInterfaces,refreshInterfaces"
    >
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

  {{-- Error --}}
  @if ($interfaceError !== '')
    <div class="alert alert-danger d-flex align-items-start mb-6" role="alert">
      <i class="bx bx-error-circle fs-4 me-2 mt-1"></i>
      <div>
        <div class="fw-semibold">Interface switch gagal dimuat</div>
        <div>{{ $interfaceError }}</div>
      </div>
    </div>
  @endif

  {{-- Loading awal --}}
  @if ($isLoading && ! $interfacesLoaded)
    <div class="card mb-6">
      <div class="card-body py-10 text-center">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <div class="fw-semibold">Mengambil status interface melalui SSH</div>
        <div class="text-muted small mt-1">
          {{ $commandPreview ?: 'Menentukan command perangkat...' }}
        </div>
      </div>
    </div>
  @endif

  @if ($interfacesLoaded)
    {{-- Metadata --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
      <span class="badge bg-label-primary">{{ $switchTypeLabel }}</span>

      @if ($isStacked)
        <span class="badge bg-label-info">
          Stack {{ $stackMemberCount }} member
        </span>
      @else
        <span class="badge bg-label-secondary">
          Standalone
        </span>
      @endif

      @if ($fromCache)
        <span class="badge bg-label-warning">Dari cache</span>
      @else
        <span class="badge bg-label-success">Data SSH terbaru</span>
      @endif

      @if ($cachedAt)
        <span class="text-muted small">Diambil {{ $cachedAt }}</span>
      @endif
    </div>

    {{-- Front panel --}}
    <div class="card mb-6">
      <div class="card-header d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
        <div>
          <h5 class="card-title mb-1">Switch Front Panel</h5>
          <div class="text-muted small">
            {{ $switchName }} — {{ $switchModel }}
          </div>
        </div>

        <div class="front-panel-legends">
          <span class="panel-legend-item">
            <span class="panel-legend-status status-up"></span>
            Up
          </span>
          <span class="panel-legend-item">
            <span class="panel-legend-status status-down"></span>
            Down
          </span>
          <span class="panel-legend-item">
            <span class="panel-legend-status status-disabled"></span>
            Disable
          </span>
          <span class="panel-legend-divider"></span>
          <span class="panel-legend-item">
            <span class="panel-legend-port legend-rj45"></span>
            GE / UTP
          </span>
          <span class="panel-legend-item">
            <span class="panel-legend-port legend-sfp"></span>
            XGE / SFP+
          </span>
          <span class="panel-legend-item">
            <span class="panel-legend-port legend-qsfp"></span>
            FGE/HGE / QSFP
          </span>
        </div>
      </div>

      <div class="card-body">
        @if ($switchMembers === [])
          <div class="text-center text-muted py-8">
            Interface fisik belum berhasil dipetakan ke front panel.
          </div>
        @else
          <div class="stack-rack">
            @foreach ($switchMembers as $member)
              <section
                class="switch-chassis"
                wire:key="switch-member-{{ $member['member'] }}"
              >
                <div class="switch-chassis-top">
                  <div class="switch-identity">
                    <div class="switch-vendor">HPE</div>
                    <div class="switch-chassis-name">{{ $switchName }}</div>
                  </div>

                  <div class="switch-member-info">
                    <span class="switch-member-badge">
                      Member {{ $member['member'] }}
                    </span>
                    <span>{{ $member['port_count'] }} interface</span>
                  </div>
                </div>

                <div class="switch-chassis-shell">
                  <div class="switch-chassis-left">
                    <div class="rack-ear rack-ear-left"></div>

                    <div class="switch-status-block">
                      <div class="switch-status-title">STATUS</div>
                      <div class="switch-status-lights">
                        <span class="chassis-led led-green"></span>
                        <span class="chassis-led"></span>
                        <span class="chassis-led"></span>
                      </div>
                      <div class="switch-member-number">{{ $member['member'] }}</div>
                    </div>
                  </div>

                  <div class="switch-chassis-center">
                    <div class="switch-vent-strip"></div>

                    <div class="switch-slot-list">
                      @foreach ($member['slots'] as $slot)
                        <div
                          class="physical-slot"
                          wire:key="switch-member-{{ $member['member'] }}-slot-{{ $slot['slot'] }}"
                        >
                          <div class="physical-slot-header">
                            <span>Member {{ $member['member'] }} / Slot {{ $slot['slot'] }}</span>
                            <span>{{ count($slot['ports']) }} port</span>
                          </div>

                          <div class="physical-port-scroll">
                            <div
                              class="physical-port-grid"
                              style="grid-template-columns: repeat({{ $slot['pair_columns'] }}, 46px);"
                            >
                              @foreach ($slot['ports'] as $port)
                                @php
                                  $portStatus = $port['status'] ?? 'down';
                                  $tooltip = implode(' | ', array_filter([
                                    $port['interface_name'] ?? null,
                                    $port['media_label'] ?? null,
                                    $port['speed'] ?? null,
                                    $port['description'] ?? null,
                                  ], static fn ($value) => $value !== null && $value !== ''));
                                @endphp

                                <div
                                  wire:key="physical-port-{{ $port['key'] }}"
                                  class="physical-port {{ $port['media_class'] }} port-status-{{ $portStatus }} port-row-{{ $port['grid_row'] }}"
                                  style="grid-column: {{ $port['grid_column'] }}; grid-row: {{ $port['grid_row'] }};"
                                  title="{{ $tooltip }}"
                                >
                                  <span class="physical-port-label">
                                    {{ $port['display_number'] }}
                                  </span>

                                  <span class="physical-port-cage">
                                    <span class="physical-port-core"></span>
                                    <span class="physical-port-led"></span>
                                  </span>
                                </div>
                              @endforeach
                            </div>
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>

                  <div class="switch-chassis-right">
                    <div class="switch-model-text">{{ $switchModel }}</div>
                    <div class="switch-host-text">{{ $routerHost ?: '-' }}</div>
                    <div class="rack-ear rack-ear-right"></div>
                  </div>
                </div>

                <div class="switch-member-summary">
                  <span>
                    <strong class="text-success">{{ $member['up_count'] }}</strong> up
                  </span>
                  <span>
                    <strong class="text-danger">{{ $member['down_count'] }}</strong> down
                  </span>
                  <span>
                    <strong class="text-secondary">{{ $member['disabled_count'] }}</strong> disable
                  </span>
                </div>
              </section>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    {{-- Summary --}}
    <div class="row g-4 mb-6">
      <div class="col-6 col-xl-3">
        <button
          type="button"
          class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'all' ? 'summary-card-active' : '' }}"
          wire:click="$set('statusFilter', 'all')"
        >
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted small">Semua Interface</div>
                <div class="fs-3 fw-bold mt-1">{{ $interfaceSummary['total'] ?? 0 }}</div>
              </div>
              <span class="avatar-initial rounded bg-label-primary p-3">
                <i class="bx bx-network-chart fs-4"></i>
              </span>
            </div>
          </div>
        </button>
      </div>

      <div class="col-6 col-xl-3">
        <button
          type="button"
          class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'up' ? 'summary-card-active' : '' }}"
          wire:click="$set('statusFilter', 'up')"
        >
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="text-muted small">Up</div>
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
        <button
          type="button"
          class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'down' ? 'summary-card-active' : '' }}"
          wire:click="$set('statusFilter', 'down')"
        >
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
        <button
          type="button"
          class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'disabled' ? 'summary-card-active' : '' }}"
          wire:click="$set('statusFilter', 'disabled')"
        >
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

    {{-- Table --}}
    <div class="card">
      <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
          <h5 class="card-title mb-1">Status Interface</h5>
          <div class="text-muted small">
            Command: <code>{{ $commandPreview }}</code>
          </div>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2">
          <div class="input-group">
            <span class="input-group-text">
              <i class="bx bx-search"></i>
            </span>
            <input
              type="search"
              class="form-control"
              style="min-width: 230px;"
              placeholder="Cari interface atau deskripsi..."
              wire:model.live.debounce.300ms="search"
            >
          </div>

          <select
            class="form-select"
            style="min-width: 160px;"
            wire:model.live="statusFilter"
          >
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
                $status = strtolower((string) ($interface['status'] ?? 'down'));
                $status = in_array($status, ['up', 'down', 'disabled'], true) ? $status : 'down';

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

              <tr wire:key="interface-row-{{ md5((string) ($interface['name'] ?? '-')) }}">
                <td class="fw-semibold text-nowrap">
                  {{ $interface['name'] ?? '-' }}
                </td>
                <td>
                  <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                  <div class="text-muted small mt-1">
                    {{ $interface['raw_status'] ?? '-' }}
                  </div>
                </td>
                <td>
                  <span class="badge bg-label-info text-uppercase">{{ $mode }}</span>
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

  <style>
    .front-panel-legends {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
      flex-wrap: wrap;
    }

    .panel-legend-item {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #69707a;
      font-size: .78rem;
      white-space: nowrap;
    }

    .panel-legend-divider {
      width: 1px;
      height: 18px;
      background: #d9dde3;
    }

    .panel-legend-status {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }

    .status-up {
      background: #31c86a;
      box-shadow: 0 0 6px rgba(49, 200, 106, .65);
    }

    .status-down {
      background: #4b5159;
    }

    .status-disabled {
      background: #a0a5ad;
    }

    .panel-legend-port {
      position: relative;
      display: inline-block;
      width: 21px;
      height: 16px;
      background: #4c525a;
      border: 2px solid #272b30;
      border-radius: 2px;
    }

    .panel-legend-port::after {
      content: '';
      position: absolute;
      inset: 3px;
      background: #1d2126;
    }

    .legend-sfp {
      width: 18px;
      background: #8d9299;
    }

    .legend-qsfp {
      width: 25px;
      background: #a5a9af;
    }

    .stack-rack {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .switch-chassis {
      min-width: 0;
    }

    .switch-chassis-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 8px;
      padding: 0 4px;
    }

    .switch-identity {
      display: flex;
      align-items: baseline;
      gap: 10px;
    }

    .switch-vendor {
      color: #23272d;
      font-size: 1rem;
      font-weight: 800;
      letter-spacing: .06em;
    }

    .switch-chassis-name {
      color: #737b85;
      font-size: .8rem;
    }

    .switch-member-info {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #737b85;
      font-size: .75rem;
    }

    .switch-member-badge {
      padding: 4px 9px;
      color: #fff;
      background: #3e454e;
      border-radius: 999px;
      font-weight: 700;
    }

    .switch-chassis-shell {
      position: relative;
      display: grid;
      grid-template-columns: 105px minmax(760px, 1fr) 145px;
      min-width: 1050px;
      min-height: 168px;
      overflow: hidden;
      background:
        linear-gradient(180deg, #34383f 0, #24282e 30%, #1d2025 100%);
      border: 1px solid #0f1114;
      border-radius: 7px;
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, .08),
        inset 0 -1px 0 rgba(0, 0, 0, .75),
        0 8px 18px rgba(20, 24, 30, .18);
    }

    .switch-chassis-left,
    .switch-chassis-right {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 18px 12px;
    }

    .switch-chassis-center {
      min-width: 0;
      padding: 18px 0 15px;
    }

    .rack-ear {
      position: absolute;
      top: 12px;
      bottom: 12px;
      width: 13px;
      background: #16191d;
      border: 1px solid #08090b;
    }

    .rack-ear::before,
    .rack-ear::after {
      content: '';
      position: absolute;
      left: 50%;
      width: 5px;
      height: 11px;
      transform: translateX(-50%);
      background: #060708;
      border-radius: 5px;
    }

    .rack-ear::before {
      top: 14px;
    }

    .rack-ear::after {
      bottom: 14px;
    }

    .rack-ear-left {
      left: 7px;
    }

    .rack-ear-right {
      right: 7px;
    }

    .switch-status-block {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 9px;
      color: #9ca3ab;
    }

    .switch-status-title {
      font-size: 8px;
      letter-spacing: .14em;
    }

    .switch-status-lights {
      display: flex;
      gap: 5px;
    }

    .chassis-led {
      width: 6px;
      height: 6px;
      background: #555c65;
      border: 1px solid #111317;
      border-radius: 50%;
    }

    .chassis-led.led-green {
      background: #5cff7f;
      box-shadow: 0 0 7px rgba(92, 255, 127, .75);
    }

    .switch-member-number {
      display: grid;
      width: 28px;
      height: 28px;
      place-items: center;
      color: #e6e8eb;
      background: #111419;
      border: 1px solid #464d56;
      border-radius: 4px;
      font-size: .75rem;
      font-weight: 800;
    }

    .switch-vent-strip {
      height: 18px;
      margin-bottom: 10px;
      background:
        radial-gradient(circle, #08090b 0 2px, transparent 2.2px) 0 0 / 10px 8px;
      opacity: .9;
    }

    .switch-slot-list {
      display: flex;
      align-items: stretch;
      gap: 14px;
      min-width: max-content;
    }

    .physical-slot {
      padding: 8px 10px 12px;
      background: #111419;
      border: 1px solid #4d535b;
      border-radius: 3px;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .025);
    }

    .physical-slot-header {
      display: flex;
      justify-content: space-between;
      gap: 22px;
      margin-bottom: 13px;
      color: #9ea5ad;
      font-size: 8px;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .physical-port-scroll {
      overflow-x: auto;
      overflow-y: visible;
      padding: 13px 2px 14px;
    }

    .physical-port-grid {
      display: grid;
      grid-template-rows: repeat(2, 38px);
      grid-auto-flow: column;
      column-gap: 5px;
      row-gap: 15px;
      width: max-content;
      min-width: max-content;
      align-items: center;
    }

    .physical-port {
      position: relative;
      width: 40px;
      height: 34px;
      cursor: help;
      transition: transform .15s ease, filter .15s ease;
    }

    .physical-port:hover {
      z-index: 5;
      transform: scale(1.08);
      filter: brightness(1.12);
    }

    .physical-port-label {
      position: absolute;
      left: 50%;
      z-index: 3;
      transform: translateX(-50%);
      color: #d5d9de;
      font-size: 8px;
      font-weight: 700;
      line-height: 1;
      white-space: nowrap;
    }

    .port-row-1 .physical-port-label {
      top: -12px;
    }

    .port-row-2 .physical-port-label {
      bottom: -12px;
    }

    .physical-port-cage {
      position: absolute;
      inset: 0;
      display: block;
      background: #777d84;
      border: 2px solid #24282e;
      border-radius: 2px;
      box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, .16),
        0 1px 2px rgba(0, 0, 0, .6);
    }

    .physical-port-core {
      position: absolute;
      display: block;
      background: #111419;
      border: 1px solid #050607;
    }

    .physical-port-led {
      position: absolute;
      top: 3px;
      right: 3px;
      z-index: 2;
      width: 5px;
      height: 5px;
      background: #535a63;
      border: 1px solid #111317;
      border-radius: 50%;
    }

    /* GE = copper / RJ45 */
    .port-rj45 .physical-port-core {
      inset: 7px 5px 5px;
      clip-path: polygon(8% 0, 92% 0, 100% 24%, 100% 100%, 0 100%, 0 24%);
    }

    .port-rj45 .physical-port-core::after {
      content: '';
      position: absolute;
      right: 5px;
      bottom: 3px;
      left: 5px;
      height: 5px;
      background: repeating-linear-gradient(
        90deg,
        #c3a766 0,
        #c3a766 2px,
        transparent 2px,
        transparent 4px
      );
    }

    /* XGE = SFP/SFP+ */
    .port-sfp,
    .port-sfp28 {
      width: 38px;
    }

    .port-sfp .physical-port-cage,
    .port-sfp28 .physical-port-cage {
      background: linear-gradient(180deg, #9da2a8, #666c73);
      border-color: #2c3137;
    }

    .port-sfp .physical-port-core,
    .port-sfp28 .physical-port-core {
      inset: 5px 5px 6px;
      background: #0d1014;
      border-color: #010203;
    }

    .port-sfp .physical-port-core::after,
    .port-sfp28 .physical-port-core::after {
      content: '';
      position: absolute;
      right: 3px;
      bottom: 4px;
      left: 3px;
      height: 4px;
      background: repeating-linear-gradient(
        90deg,
        #d6c392 0,
        #d6c392 2px,
        transparent 2px,
        transparent 4px
      );
    }

    .port-sfp28 .physical-port-cage {
      box-shadow:
        inset 0 0 0 1px rgba(116, 188, 255, .35),
        0 1px 2px rgba(0, 0, 0, .6);
    }

    /* FGE/HGE = QSFP/QSFP28 */
    .port-qsfp,
    .port-qsfp28 {
      width: 44px;
    }

    .port-qsfp .physical-port-cage,
    .port-qsfp28 .physical-port-cage {
      background: linear-gradient(180deg, #aeb2b7, #6f747a);
      border-color: #2c3137;
    }

    .port-qsfp .physical-port-core,
    .port-qsfp28 .physical-port-core {
      inset: 5px 4px 6px;
      background: #0c0f13;
      border-color: #010203;
    }

    .port-qsfp .physical-port-core::after,
    .port-qsfp28 .physical-port-core::after {
      content: '';
      position: absolute;
      right: 4px;
      bottom: 4px;
      left: 4px;
      height: 5px;
      background: repeating-linear-gradient(
        90deg,
        #d8c792 0,
        #d8c792 2px,
        transparent 2px,
        transparent 4px
      );
    }

    .port-qsfp28 .physical-port-cage {
      box-shadow:
        inset 0 0 0 1px rgba(255, 191, 93, .36),
        0 1px 2px rgba(0, 0, 0, .6);
    }

    /* Status */
    .port-status-up .physical-port-cage {
      border-color: #1db65a;
      box-shadow:
        inset 0 0 0 1px rgba(88, 255, 142, .34),
        0 0 9px rgba(31, 203, 97, .7);
    }

    .port-status-up .physical-port-led {
      background: #69ff82;
      border-color: #1ca942;
      box-shadow: 0 0 7px rgba(105, 255, 130, .95);
    }

    .port-status-down .physical-port-cage {
      filter: brightness(.66);
    }

    .port-status-disabled {
      opacity: .48;
    }

    .port-status-disabled .physical-port-cage {
      filter: grayscale(1);
      border-color: #8f969e;
    }

    .switch-chassis-right {
      flex-direction: column;
      align-items: flex-end;
      justify-content: center;
      padding-right: 28px;
      color: #aab0b7;
      text-align: right;
    }

    .switch-model-text {
      max-width: 110px;
      font-size: .72rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .switch-host-text {
      margin-top: 6px;
      color: #747c86;
      font-family: monospace;
      font-size: .66rem;
      word-break: break-all;
    }

    .switch-member-summary {
      display: flex;
      justify-content: flex-end;
      gap: 14px;
      padding: 7px 5px 0;
      color: #747c86;
      font-size: .72rem;
    }

    .summary-card {
      color: inherit;
      transition: transform .2s ease, box-shadow .2s ease;
    }

    .summary-card:hover,
    .summary-card-active {
      transform: translateY(-2px);
      box-shadow: 0 .25rem 1rem rgba(34, 48, 62, .12);
    }

    @media (max-width: 1199.98px) {
      .front-panel-legends {
        justify-content: flex-start;
      }

      .switch-chassis {
        overflow-x: auto;
        padding-bottom: 8px;
      }
    }

    @media (max-width: 767.98px) {
      .panel-legend-divider {
        display: none;
      }

      .switch-chassis-top {
        align-items: flex-start;
      }

      .switch-member-info {
        align-items: flex-end;
        flex-direction: column;
        gap: 4px;
      }
    }
  </style>
</div>
