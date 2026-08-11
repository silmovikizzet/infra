<div wire:init="loadInterfaces">

  {{-- =========================================================
  HEADER
  ========================================================== --}}
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


  {{-- =========================================================
  ERROR
  ========================================================== --}}
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


  {{-- =========================================================
  LOADING
  ========================================================== --}}
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

  @php
  /*
  * Interface yang memang tidak perlu ditampilkan.
  */
  $ignoredInterfaces = [
  'MGE0/0/0',
  'MGE0/0/1',
  'NULL0',
  'REG0',
  ];


  /*
  * Filter table interface.
  */
  $displayInterfaces = collect($filteredInterfaces)
  ->reject(function ($interface) use ($ignoredInterfaces) {
  $name = strtoupper(
  trim((string) ($interface['name'] ?? ''))
  );

  return in_array($name, $ignoredInterfaces, true);
  })
  ->values();


  /*
  * Summary berdasarkan interface yang benar-benar
  * ditampilkan.
  */
  $allDisplayInterfaces = collect($interfaces ?? $filteredInterfaces)
  ->reject(function ($interface) use ($ignoredInterfaces) {
  $name = strtoupper(
  trim((string) ($interface['name'] ?? ''))
  );

  return in_array($name, $ignoredInterfaces, true);
  })
  ->values();


  $displaySummary = [
  'total' => $allDisplayInterfaces->count(),

  'up' => $allDisplayInterfaces
  ->filter(
  fn ($interface) =>
  strtolower(
  (string) ($interface['status'] ?? '')
  ) === 'up'
  )
  ->count(),

  'down' => $allDisplayInterfaces
  ->filter(
  fn ($interface) =>
  strtolower(
  (string) ($interface['status'] ?? '')
  ) === 'down'
  )
  ->count(),

  'disabled' => $allDisplayInterfaces
  ->filter(
  fn ($interface) =>
  strtolower(
  (string) ($interface['status'] ?? '')
  ) === 'disabled'
  )
  ->count(),
  ];
  @endphp


  {{-- =====================================================
  METADATA
  ====================================================== --}}
  <div class="d-flex flex-wrap align-items-center gap-2 mb-4">

    <span class="badge bg-label-primary">
      {{ $switchTypeLabel }}
    </span>

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


  {{-- =====================================================
  PORT FRONT PANEL
  ====================================================== --}}
  <div class="card mb-6">

    <div class="card-header d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">

      <div>
        <h5 class="card-title mb-1">
          Switch Front Panel
        </h5>

        <div class="text-muted small">
          Tampilan fisik interface switch
        </div>
      </div>


      {{-- LEGEND --}}
      <div class="front-panel-legends">

        <span class="panel-legend-item">
          <span class="legend-status legend-up"></span>
          Up
        </span>

        <span class="panel-legend-item">
          <span class="legend-status legend-down"></span>
          Down
        </span>

        <span class="panel-legend-item">
          <span class="legend-status legend-disabled"></span>
          Disable
        </span>

        <span class="legend-divider"></span>

        <span class="panel-legend-item">
          <span class="legend-port legend-rj45"></span>
          GE / UTP
        </span>

        <span class="panel-legend-item">
          <span class="legend-port legend-sfp"></span>
          XGE / SFP
        </span>

        <span class="panel-legend-item">
          <span class="legend-port legend-qsfp"></span>
          FGE / QSFP
        </span>

      </div>

    </div>


    <div class="card-body">

      @if ($switchMembers === [])

      <div class="text-center text-muted py-8">
        Interface fisik belum berhasil dipetakan ke front panel.
      </div>

      @else

      <div class="switch-panel-list">

        @foreach ($switchMembers as $member)

        @php
        /*
        * Filter port member.
        */
        $memberSlots = collect($member['slots'] ?? [])
        ->map(function ($slot) use ($ignoredInterfaces) {

        $slot['ports'] = collect($slot['ports'] ?? [])
        ->reject(function ($port) use ($ignoredInterfaces) {

        $name = strtoupper(
        trim(
        (string) (
        $port['interface_name']
        ?? $port['name']
        ?? ''
        )
        )
        );

        return in_array(
        $name,
        $ignoredInterfaces,
        true
        );
        })
        ->values()
        ->all();

        return $slot;
        })
        ->filter(
        fn ($slot) =>
        count($slot['ports'] ?? []) > 0
        )
        ->values();
        @endphp


        @if ($memberSlots->isNotEmpty())

        <div class="switch-member-panel" wire:key="switch-member-{{ $member['member'] }}">

          @if ($isStacked)
          <div class="switch-member-title">
            Switch {{ $member['member'] }}
          </div>
          @endif


          @foreach ($memberSlots as $slot)

          @php
          /*
          * Kelompokkan port menjadi pasangan:
          *
          * 1 atas
          * 2 bawah
          *
          * 3 atas
          * 4 bawah
          *
          * dst.
          */
          $columns = collect($slot['ports'])
          ->groupBy(
          fn ($port) =>
          (int) (
          $port['grid_column']
          ?? 1
          )
          )
          ->sortKeys();
          @endphp


          <div class="switch-port-group" wire:key="member-{{ $member['member'] }}-slot-{{ $slot['slot'] }}">

            <div class="switch-port-row">

              @foreach ($columns as $column => $columnPorts)

              @php
              $topPort = collect($columnPorts)
              ->first(
              fn ($item) =>
              (int) (
              $item['grid_row']
              ?? 1
              ) === 1
              );

              $bottomPort = collect($columnPorts)
              ->first(
              fn ($item) =>
              (int) (
              $item['grid_row']
              ?? 1
              ) === 2
              );
              @endphp


              <div class="port-column" wire:key="port-column-{{ $member['member'] }}-{{ $slot['slot'] }}-{{ $column }}">

                {{-- =================================
                PORT ATAS
                ================================== --}}
                <div class="port-position port-position-top">

                  @if ($topPort)

                  @php
                  $port = $topPort;

                  $status = strtolower(
                  (string) (
                  $port['status']
                  ?? 'down'
                  )
                  );

                  if (! in_array(
                  $status,
                  [
                  'up',
                  'down',
                  'disabled',
                  ],
                  true
                  )) {
                  $status = 'down';
                  }


                  $interfaceName = strtoupper(
                  (string) (
                  $port['interface_name']
                  ?? ''
                  )
                  );

                  $mediaLabel = strtoupper(
                  (string) (
                  $port['media_label']
                  ?? ''
                  )
                  );

                  $rawMediaClass = strtolower(
                  (string) (
                  $port['media_class']
                  ?? ''
                  )
                  );


                  /*
                  * FGE / HGE = QSFP
                  */
                  if (
                  str_contains(
                  $rawMediaClass,
                  'qsfp'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'FGE'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'HGE'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'QSFP'
                  )
                  ||
                  str_contains(
                  $mediaLabel,
                  'QSFP'
                  )
                  ) {

                  $mediaClass = 'port-qsfp';

                  /*
                  * XGE = SFP/SFP+
                  */
                  } elseif (
                  str_contains(
                  $rawMediaClass,
                  'sfp'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'XGE'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'SFP'
                  )
                  ||
                  str_contains(
                  $mediaLabel,
                  'SFP'
                  )
                  ) {

                  $mediaClass = 'port-sfp';

                  /*
                  * GE = RJ45
                  */
                  } else {

                  $mediaClass = 'port-rj45';
                  }


                  $tooltip = implode(
                  ' | ',
                  array_filter([
                  $port['interface_name']
                  ?? null,

                  $port['media_label']
                  ?? null,

                  $port['speed']
                  ?? null,

                  $port['description']
                  ?? null,
                  ], static fn ($value) =>
                  $value !== null
                  &&
                  $value !== ''
                  )
                  );
                  @endphp


                  <div class="
                                                                        physical-port
                                                                        {{ $mediaClass }}
                                                                        port-status-{{ $status }}
                                                                    " title="{{ $tooltip }}"
                    wire:key="physical-port-{{ $port['key'] }}">

                    <span class="physical-port-label">
                      {{ $port['display_number'] }}
                    </span>


                    <span class="physical-port-frame">

                      <span class="physical-port-hole"></span>

                    </span>

                  </div>

                  @else

                  <div class="physical-port-placeholder"></div>

                  @endif

                </div>


                {{-- =================================
                PORT BAWAH
                ================================== --}}
                <div class="port-position port-position-bottom">

                  @if ($bottomPort)

                  @php
                  $port = $bottomPort;

                  $status = strtolower(
                  (string) (
                  $port['status']
                  ?? 'down'
                  )
                  );

                  if (! in_array(
                  $status,
                  [
                  'up',
                  'down',
                  'disabled',
                  ],
                  true
                  )) {
                  $status = 'down';
                  }


                  $interfaceName = strtoupper(
                  (string) (
                  $port['interface_name']
                  ?? ''
                  )
                  );

                  $mediaLabel = strtoupper(
                  (string) (
                  $port['media_label']
                  ?? ''
                  )
                  );

                  $rawMediaClass = strtolower(
                  (string) (
                  $port['media_class']
                  ?? ''
                  )
                  );


                  if (
                  str_contains(
                  $rawMediaClass,
                  'qsfp'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'FGE'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'HGE'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'QSFP'
                  )
                  ||
                  str_contains(
                  $mediaLabel,
                  'QSFP'
                  )
                  ) {

                  $mediaClass = 'port-qsfp';

                  } elseif (
                  str_contains(
                  $rawMediaClass,
                  'sfp'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'XGE'
                  )
                  ||
                  str_contains(
                  $interfaceName,
                  'SFP'
                  )
                  ||
                  str_contains(
                  $mediaLabel,
                  'SFP'
                  )
                  ) {

                  $mediaClass = 'port-sfp';

                  } else {

                  $mediaClass = 'port-rj45';
                  }


                  $tooltip = implode(
                  ' | ',
                  array_filter([
                  $port['interface_name']
                  ?? null,

                  $port['media_label']
                  ?? null,

                  $port['speed']
                  ?? null,

                  $port['description']
                  ?? null,
                  ], static fn ($value) =>
                  $value !== null
                  &&
                  $value !== ''
                  )
                  );
                  @endphp


                  <div class="
                                                                        physical-port
                                                                        {{ $mediaClass }}
                                                                        port-status-{{ $status }}
                                                                    " title="{{ $tooltip }}"
                    wire:key="physical-port-{{ $port['key'] }}">

                    <span class="physical-port-label">
                      {{ $port['display_number'] }}
                    </span>


                    <span class="physical-port-frame">

                      <span class="physical-port-hole"></span>

                    </span>

                  </div>

                  @else

                  <div class="physical-port-placeholder"></div>

                  @endif

                </div>

              </div>

              @endforeach

            </div>

          </div>

          @endforeach

        </div>

        @endif

        @endforeach

      </div>

      @endif

    </div>

  </div>


  {{-- =====================================================
  SUMMARY
  ====================================================== --}}
  <div class="row g-4 mb-6">

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
                {{ $displaySummary['total'] }}
              </div>
            </div>

            <span class="avatar-initial rounded bg-label-primary p-3">
              <i class="bx bx-network-chart fs-4"></i>
            </span>

          </div>
        </div>
      </button>
    </div>


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
                {{ $displaySummary['up'] }}
              </div>
            </div>

            <span class="avatar-initial rounded bg-label-success p-3">
              <i class="bx bx-check-circle fs-4"></i>
            </span>

          </div>
        </div>
      </button>
    </div>


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
                {{ $displaySummary['down'] }}
              </div>
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
        class="card summary-card h-100 w-100 border-0 text-start {{ $statusFilter === 'disabled' ? 'summary-card-active' : '' }}"
        wire:click="$set('statusFilter', 'disabled')">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">

            <div>
              <div class="text-muted small">
                Disable
              </div>

              <div class="fs-3 fw-bold text-secondary mt-1">
                {{ $displaySummary['disabled'] }}
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


  {{-- =====================================================
  TABLE
  ====================================================== --}}
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

          @forelse ($displayInterfaces as $interface)

          @php
          $status = strtolower(
          (string) ($interface['status'] ?? 'down')
          );

          $status = in_array(
          $status,
          [
          'up',
          'down',
          'disabled',
          ],
          true
          )
          ? $status
          : 'down';


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



  <style>
    /* =========================================================
         * LEGEND
         * ========================================================= */

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

    .legend-divider {
      width: 1px;
      height: 18px;

      background: #d9dde3;
    }

    .legend-status {
      width: 11px;
      height: 11px;

      border: 1px solid #1e2226;

      border-radius: 2px;
    }

    .legend-up {
      background: #28ca61;
    }

    .legend-down {
      background: #595d60;
    }

    .legend-disabled {
      position: relative;

      background: #595c5f;

      overflow: hidden;
    }

    .legend-disabled::before,
    .legend-disabled::after {
      content: '';

      position: absolute;

      top: 50%;
      left: 50%;

      width: 85%;
      height: 2px;

      background: #ff2d2d;

      border-radius: 2px;

      transform-origin: center;
    }

    .legend-disabled::before {
      transform:
        translate(-50%, -50%) rotate(45deg);
    }

    .legend-disabled::after {
      transform:
        translate(-50%, -50%) rotate(-45deg);
    }


    .legend-port {
      position: relative;

      display: block;

      height: 19px;

      padding: 2px;

      background: #dadcde;

      border: 1px solid #83878b;
    }

    .legend-port::after {
      content: '';

      position: absolute;

      inset: 3px;

      background: #55595d;

      border: 1px solid #17191b;
    }

    .legend-rj45 {
      width: 24px;
    }

    .legend-rj45::after {
      clip-path: polygon(0 0,
          100% 0,
          100% 70%,
          84% 70%,
          84% 85%,
          67% 85%,
          67% 100%,
          33% 100%,
          33% 85%,
          16% 85%,
          16% 70%,
          0 70%);
    }

    .legend-sfp {
      width: 24px;
    }

    .legend-qsfp {
      width: 38px;
    }


    /* =========================================================
         * PANEL
         * ========================================================= */

    .switch-panel-list {
      display: flex;
      flex-direction: column;

      gap: 26px;
    }


    /* =========================================================
         * STACK MEMBER
         * ========================================================= */

    .switch-member-panel {
      min-width: 0;
    }

    .switch-member-title {
      margin-bottom: 10px;

      color: #56606a;

      font-size: .8rem;
      font-weight: 700;
    }


    /* =========================================================
         * SLOT / GROUP
         * ========================================================= */

    .switch-port-group {
      width: 100%;

      overflow-x: auto;
      overflow-y: hidden;

      padding:
        20px 10px 20px;

      background: #f3f4f5;

      border: 1px solid #c7cbcf;

      border-radius: 2px;
    }

    .switch-port-row {
      display: flex;

      align-items: flex-start;

      gap: 5px;

      width: max-content;

      min-width: max-content;
    }


    /* =========================================================
         * PORT COLUMN
         *
         * 1
         * 2
         *
         * lalu
         *
         * 3
         * 4
         * ========================================================= */

    .port-column {
      display: flex;

      flex: 0 0 auto;
      flex-direction: column;

      align-items: center;

      gap: 7px;
    }

    .port-position {
      display: flex;

      align-items: center;
      justify-content: center;

      min-height: 42px;
    }

    .physical-port-placeholder {
      width: 48px;
      height: 38px;

      visibility: hidden;
    }


    /* =========================================================
         * PORT
         * ========================================================= */

    .physical-port {
      position: relative;

      flex: 0 0 auto;

      height: 38px;

      cursor: help;

      transition:
        transform .12s ease,
        filter .12s ease;
    }

    .physical-port:hover {
      z-index: 20;

      transform: scale(1.05);

      filter: brightness(1.05);
    }


    /* =========================================================
         * NUMBER
         * ========================================================= */

    .physical-port-label {
      position: absolute;

      left: 50%;

      z-index: 5;

      transform: translateX(-50%);

      color: #52585e;

      font-size: 9px;
      font-weight: 700;

      line-height: 1;

      white-space: nowrap;

      pointer-events: none;
    }

    .port-position-top .physical-port-label {
      top: -12px;
    }

    .port-position-bottom .physical-port-label {
      bottom: -12px;
    }


    /* =========================================================
         * OUTER PORT FRAME
         * ========================================================= */

    .physical-port-frame {
      position: absolute;

      inset: 0;

      display: block;

      padding: 4px;

      background:
        linear-gradient(180deg,
          #f0f1f2 0%,
          #d4d6d8 45%,
          #afb3b6 100%);

      border: 1px solid #878c90;

      box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, .8),
        0 1px 1px rgba(0, 0, 0, .2);
    }


    /* =========================================================
         * INNER PORT
         * ========================================================= */

    .physical-port-hole {
      position: absolute;

      inset: 4px;

      display: block;

      background: #595d60;

      border: 2px solid #171a1c;
    }


    /* =========================================================
         * GE / RJ45 / UTP
         * ========================================================= */

    .port-rj45 {
      width: 48px;
    }

    .port-rj45 .physical-port-hole {
      clip-path: polygon(0 0,
          100% 0,

          100% 69%,

          86% 69%,
          86% 84%,

          68% 84%,
          68% 100%,

          32% 100%,
          32% 84%,

          14% 84%,
          14% 69%,

          0 69%);
    }


    /* =========================================================
         * XGE / SFP
         * ========================================================= */

    .port-sfp {
      width: 48px;
    }

    .port-sfp .physical-port-hole {
      clip-path: none;
    }


    /* =========================================================
         * FGE / HGE / QSFP
         *
         * SFP YANG DILEBARKAN
         * ========================================================= */

    .port-qsfp {
      width: 76px;
    }

    .port-qsfp .physical-port-hole {
      clip-path: none;
    }


    /* =========================================================
         * UP
         * ========================================================= */

    .port-status-up .physical-port-hole {
      background: #25c95d;
    }


    /* =========================================================
         * DOWN
         * ========================================================= */

    .port-status-down .physical-port-hole {
      background: #595c5f;
    }


    /* =========================================================
 * DISABLED
 * Port abu-abu + silang merah
 * ========================================================= */

    .port-status-disabled .physical-port-hole {
      background: #595c5f;
      overflow: hidden;
    }

    /* garis silang pertama */
    .port-status-disabled .physical-port-hole::before,
    .port-status-disabled .physical-port-hole::after {
      content: '';

      position: absolute;

      top: 50%;
      left: 50%;

      width: 75%;
      height: 3px;

      background: #ff2d2d;

      border-radius: 3px;

      box-shadow:
        0 0 2px rgba(0, 0, 0, .8),
        0 0 4px rgba(255, 45, 45, .45);

      transform-origin: center;
    }

    /* \ */
    .port-status-disabled .physical-port-hole::before {
      transform:
        translate(-50%, -50%) rotate(45deg);
    }

    /* / */
    .port-status-disabled .physical-port-hole::after {
      transform:
        translate(-50%, -50%) rotate(-45deg);
    }

    /* =========================================================
         * SUMMARY
         * ========================================================= */

    .summary-card {
      color: inherit;

      transition:
        transform .2s ease,
        box-shadow .2s ease;
    }

    .summary-card:hover,
    .summary-card-active {
      transform: translateY(-2px);

      box-shadow:
        0 .25rem 1rem rgba(34, 48, 62, .12);
    }


    /* =========================================================
         * SCROLLBAR
         * ========================================================= */

    .switch-port-group::-webkit-scrollbar {
      height: 7px;
    }

    .switch-port-group::-webkit-scrollbar-track {
      background: #e6e8ea;
    }

    .switch-port-group::-webkit-scrollbar-thumb {
      background: #a7adb2;

      border-radius: 20px;
    }


    /* =========================================================
         * RESPONSIVE
         * ========================================================= */

    @media (max-width: 1199.98px) {

      .front-panel-legends {
        justify-content: flex-start;
      }

    }


    @media (max-width: 767.98px) {

      .legend-divider {
        display: none;
      }

    }
  </style>

</div>
