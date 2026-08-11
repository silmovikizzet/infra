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
  INITIAL LOADING
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
  SWITCH FRONT PANEL
  ====================================================== --}}
  <div class="card mb-6">
    <div class="card-header d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
      <div>
        <h5 class="card-title mb-1">
          Switch Front Panel
        </h5>

        <div class="text-muted small">
          {{ $switchName }} — {{ $switchModel }}
        </div>
      </div>


      {{-- LEGEND --}}
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
          <span class="panel-legend-port legend-rj45">
            <span></span>
          </span>
          GE / UTP
        </span>

        <span class="panel-legend-item">
          <span class="panel-legend-port legend-sfp">
            <span></span>
          </span>
          XGE / SFP+
        </span>

        <span class="panel-legend-item">
          <span class="panel-legend-port legend-qsfp">
            <span></span>
          </span>
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

      <div class="stack-rack">

        @foreach ($switchMembers as $member)

        <section class="switch-chassis" wire:key="switch-member-{{ $member['member'] }}">

          {{-- MEMBER HEADER --}}
          <div class="switch-chassis-top">

            <div class="switch-identity">
              <div class="switch-vendor">
                HPE
              </div>

              <div class="switch-chassis-name">
                {{ $switchName }}
              </div>
            </div>


            <div class="switch-member-info">

              <span class="switch-member-badge">
                Member {{ $member['member'] }}
              </span>

              <span>
                {{ $member['port_count'] }} interface
              </span>

            </div>
          </div>


          {{-- CHASSIS --}}
          <div class="switch-chassis-shell">

            {{-- LEFT --}}
            <div class="switch-chassis-left">

              <div class="rack-ear rack-ear-left"></div>


              <div class="switch-status-block">

                <div class="switch-status-title">
                  STATUS
                </div>

                <div class="switch-status-lights">
                  <span class="chassis-led led-green"></span>
                  <span class="chassis-led"></span>
                  <span class="chassis-led"></span>
                </div>

                <div class="switch-member-number">
                  {{ $member['member'] }}
                </div>

              </div>
            </div>


            {{-- CENTER --}}
            <div class="switch-chassis-center">

              <div class="switch-vent-strip"></div>


              <div class="switch-slot-scroll">

                <div class="switch-slot-list">

                  @foreach ($member['slots'] as $slot)

                  @php
                  /*
                  * Port dikelompokkan berdasarkan grid_column.
                  *
                  * Contoh:
                  *
                  * Column 1:
                  * row 1 = port 1
                  * row 2 = port 2
                  *
                  * Column 2:
                  * row 1 = port 3
                  * row 2 = port 4
                  *
                  * Dengan metode ini QSFP boleh lebih lebar
                  * tanpa menimpa port berikutnya.
                  */
                  $portColumns = collect($slot['ports'])
                  ->groupBy(
                  fn ($port) => (int) ($port['grid_column'] ?? 1)
                  )
                  ->sortKeys();
                  @endphp


                  <div class="physical-slot" wire:key="switch-member-{{ $member['member'] }}-slot-{{ $slot['slot'] }}">

                    <div class="physical-slot-header">

                      <span>
                        Member {{ $member['member'] }}
                        /
                        Slot {{ $slot['slot'] }}
                      </span>

                      <span>
                        {{ count($slot['ports']) }} port
                      </span>

                    </div>


                    <div class="physical-port-scroll">

                      <div class="physical-port-grid">

                        @foreach ($portColumns as $column => $columnPorts)

                        @php
                        $topPort = collect($columnPorts)
                        ->first(
                        fn ($item) =>
                        (int) ($item['grid_row'] ?? 1) === 1
                        );

                        $bottomPort = collect($columnPorts)
                        ->first(
                        fn ($item) =>
                        (int) ($item['grid_row'] ?? 1) === 2
                        );
                        @endphp


                        <div class="physical-port-pair"
                          wire:key="port-pair-{{ $member['member'] }}-{{ $slot['slot'] }}-{{ $column }}">

                          {{-- =========================================
                          TOP PORT
                          ========================================== --}}
                          <div class="physical-port-position position-top">

                            @if ($topPort)

                            @php
                            $port = $topPort;

                            $portStatus = strtolower(
                            (string) ($port['status'] ?? 'down')
                            );

                            if (! in_array(
                            $portStatus,
                            ['up', 'down', 'disabled'],
                            true
                            )) {
                            $portStatus = 'down';
                            }


                            /*
                            * Normalisasi tipe media.
                            *
                            * Prioritas:
                            * FGE/HGE/QSFP => QSFP
                            * XGE/SFP => SFP
                            * GE => RJ45
                            */
                            $interfaceName = strtoupper(
                            (string) ($port['interface_name'] ?? '')
                            );

                            $mediaLabel = strtoupper(
                            (string) ($port['media_label'] ?? '')
                            );

                            $rawMediaClass = strtolower(
                            (string) ($port['media_class'] ?? '')
                            );


                            if (
                            str_contains($rawMediaClass, 'qsfp') ||
                            str_contains($interfaceName, 'FGE') ||
                            str_contains($interfaceName, 'HGE') ||
                            str_contains($interfaceName, 'QSFP') ||
                            str_contains($mediaLabel, 'QSFP')
                            ) {
                            $mediaClass = 'port-qsfp';
                            } elseif (
                            str_contains($rawMediaClass, 'sfp') ||
                            str_contains($interfaceName, 'XGE') ||
                            str_contains($interfaceName, 'SFP') ||
                            str_contains($mediaLabel, 'SFP')
                            ) {
                            $mediaClass = 'port-sfp';
                            } else {
                            $mediaClass = 'port-rj45';
                            }


                            $tooltip = implode(
                            ' | ',
                            array_filter([
                            $port['interface_name'] ?? null,
                            $port['media_label'] ?? null,
                            $port['speed'] ?? null,
                            $port['description'] ?? null,
                            ], static fn ($value) =>
                            $value !== null &&
                            $value !== ''
                            )
                            );
                            @endphp


                            <div class="physical-port {{ $mediaClass }} port-status-{{ $portStatus }}"
                              title="{{ $tooltip }}" wire:key="physical-port-{{ $port['key'] }}">

                              <span class="physical-port-label">
                                {{ $port['display_number'] }}
                              </span>


                              <span class="physical-port-cage">

                                <span class="physical-port-core"></span>

                                <span class="physical-port-led"></span>

                              </span>

                            </div>

                            @else

                            <div class="physical-port-empty"></div>

                            @endif

                          </div>


                          {{-- =========================================
                          BOTTOM PORT
                          ========================================== --}}
                          <div class="physical-port-position position-bottom">

                            @if ($bottomPort)

                            @php
                            $port = $bottomPort;

                            $portStatus = strtolower(
                            (string) ($port['status'] ?? 'down')
                            );

                            if (! in_array(
                            $portStatus,
                            ['up', 'down', 'disabled'],
                            true
                            )) {
                            $portStatus = 'down';
                            }


                            $interfaceName = strtoupper(
                            (string) ($port['interface_name'] ?? '')
                            );

                            $mediaLabel = strtoupper(
                            (string) ($port['media_label'] ?? '')
                            );

                            $rawMediaClass = strtolower(
                            (string) ($port['media_class'] ?? '')
                            );


                            if (
                            str_contains($rawMediaClass, 'qsfp') ||
                            str_contains($interfaceName, 'FGE') ||
                            str_contains($interfaceName, 'HGE') ||
                            str_contains($interfaceName, 'QSFP') ||
                            str_contains($mediaLabel, 'QSFP')
                            ) {
                            $mediaClass = 'port-qsfp';
                            } elseif (
                            str_contains($rawMediaClass, 'sfp') ||
                            str_contains($interfaceName, 'XGE') ||
                            str_contains($interfaceName, 'SFP') ||
                            str_contains($mediaLabel, 'SFP')
                            ) {
                            $mediaClass = 'port-sfp';
                            } else {
                            $mediaClass = 'port-rj45';
                            }


                            $tooltip = implode(
                            ' | ',
                            array_filter([
                            $port['interface_name'] ?? null,
                            $port['media_label'] ?? null,
                            $port['speed'] ?? null,
                            $port['description'] ?? null,
                            ], static fn ($value) =>
                            $value !== null &&
                            $value !== ''
                            )
                            );
                            @endphp


                            <div class="physical-port {{ $mediaClass }} port-status-{{ $portStatus }}"
                              title="{{ $tooltip }}" wire:key="physical-port-{{ $port['key'] }}">

                              <span class="physical-port-label">
                                {{ $port['display_number'] }}
                              </span>


                              <span class="physical-port-cage">

                                <span class="physical-port-core"></span>

                                <span class="physical-port-led"></span>

                              </span>

                            </div>

                            @else

                            <div class="physical-port-empty"></div>

                            @endif

                          </div>

                        </div>

                        @endforeach

                      </div>

                    </div>

                  </div>

                  @endforeach

                </div>

              </div>

            </div>


            {{-- RIGHT --}}
            <div class="switch-chassis-right">

              <div class="switch-model-text">
                {{ $switchModel }}
              </div>

              <div class="switch-host-text">
                {{ $routerHost ?: '-' }}
              </div>

              <div class="rack-ear rack-ear-right"></div>

            </div>

          </div>


          {{-- MEMBER SUMMARY --}}
          <div class="switch-member-summary">

            <span>
              <strong class="text-success">
                {{ $member['up_count'] }}
              </strong>
              up
            </span>

            <span>
              <strong class="text-danger">
                {{ $member['down_count'] }}
              </strong>
              down
            </span>

            <span>
              <strong class="text-secondary">
                {{ $member['disabled_count'] }}
              </strong>
              disable
            </span>

          </div>

        </section>

        @endforeach

      </div>

      @endif

    </div>
  </div>


  {{-- =====================================================
  SUMMARY
  ====================================================== --}}
  <div class="row g-4 mb-6">

    {{-- ALL --}}
    <div class="col-6 col-xl-3">

      <button type="button" class="
                        card
                        summary-card
                        h-100
                        w-100
                        border-0
                        text-start
                        {{ $statusFilter === 'all' ? 'summary-card-active' : '' }}
                    " wire:click="$set('statusFilter', 'all')">

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


    {{-- UP --}}
    <div class="col-6 col-xl-3">

      <button type="button" class="
                        card
                        summary-card
                        h-100
                        w-100
                        border-0
                        text-start
                        {{ $statusFilter === 'up' ? 'summary-card-active' : '' }}
                    " wire:click="$set('statusFilter', 'up')">

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


    {{-- DOWN --}}
    <div class="col-6 col-xl-3">

      <button type="button" class="
                        card
                        summary-card
                        h-100
                        w-100
                        border-0
                        text-start
                        {{ $statusFilter === 'down' ? 'summary-card-active' : '' }}
                    " wire:click="$set('statusFilter', 'down')">

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


    {{-- DISABLED --}}
    <div class="col-6 col-xl-3">

      <button type="button" class="
                        card
                        summary-card
                        h-100
                        w-100
                        border-0
                        text-start
                        {{ $statusFilter === 'disabled' ? 'summary-card-active' : '' }}
                    " wire:click="$set('statusFilter', 'disabled')">

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


  {{-- =====================================================
  INTERFACE TABLE
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
          $status = strtolower(
          (string) ($interface['status'] ?? 'down')
          );

          $status = in_array(
          $status,
          ['up', 'down', 'disabled'],
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
         * FRONT PANEL LEGEND
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

    .panel-legend-divider {
      width: 1px;
      height: 18px;

      background: #d9dde3;
    }


    /* =========================================================
         * STATUS LEGEND
         * ========================================================= */

    .panel-legend-status {
      display: inline-block;

      width: 10px;
      height: 10px;

      border-radius: 50%;
    }

    .status-up {
      background: #20c75a;

      box-shadow:
        0 0 6px rgba(32, 199, 90, .65);
    }

    .status-down {
      background: #55595e;
    }

    .status-disabled {
      background:
        repeating-linear-gradient(135deg,
          #85898e 0,
          #85898e 3px,
          #c8cacc 3px,
          #c8cacc 5px);
    }


    /* =========================================================
         * PORT LEGEND
         * ========================================================= */

    .panel-legend-port {
      position: relative;

      display: inline-flex;
      align-items: center;
      justify-content: center;

      height: 18px;

      padding: 2px;

      background: #d6d8da;

      border: 1px solid #7d8185;

      border-radius: 1px;

      box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, .5);
    }

    .panel-legend-port>span {
      display: block;

      width: 100%;
      height: 100%;

      background: #55595e;

      border: 1px solid #282b2e;
    }

    .legend-rj45 {
      width: 23px;
    }

    .legend-rj45>span {
      clip-path: polygon(0 0,
          100% 0,
          100% 72%,
          83% 72%,
          83% 87%,
          65% 87%,
          65% 100%,
          35% 100%,
          35% 87%,
          17% 87%,
          17% 72%,
          0 72%);
    }

    .legend-sfp {
      width: 24px;
    }

    .legend-qsfp {
      width: 35px;
    }


    /* =========================================================
         * STACK
         * ========================================================= */

    .stack-rack {
      display: flex;
      flex-direction: column;

      gap: 22px;
    }

    .switch-chassis {
      min-width: 0;
    }


    /* =========================================================
         * CHASSIS HEADER
         * ========================================================= */

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


    /* =========================================================
         * CHASSIS
         * ========================================================= */

    .switch-chassis-shell {
      position: relative;

      display: grid;

      grid-template-columns:
        95px minmax(760px, 1fr) 145px;

      min-width: 1050px;
      min-height: 178px;

      overflow: hidden;

      background:
        linear-gradient(180deg,
          #35393f 0,
          #272b30 35%,
          #1d2024 100%);

      border: 1px solid #0d0f11;

      border-radius: 7px;

      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, .09),
        inset 0 -1px 0 rgba(0, 0, 0, .8),
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

      padding: 18px 0 16px;
    }


    /* =========================================================
         * RACK EAR
         * ========================================================= */

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


    /* =========================================================
         * SWITCH STATUS
         * ========================================================= */

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

      box-shadow:
        0 0 7px rgba(92, 255, 127, .75);
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


    /* =========================================================
         * VENT
         * ========================================================= */

    .switch-vent-strip {
      height: 16px;

      margin-bottom: 8px;

      background:
        radial-gradient(circle,
          #08090b 0 2px,
          transparent 2.2px) 0 0 / 10px 8px;

      opacity: .9;
    }


    /* =========================================================
         * SLOTS
         * ========================================================= */

    .switch-slot-scroll {
      width: 100%;

      overflow-x: auto;
      overflow-y: hidden;

      padding-bottom: 3px;
    }

    .switch-slot-list {
      display: flex;

      align-items: stretch;

      gap: 14px;

      width: max-content;
      min-width: 100%;
    }

    .physical-slot {
      flex: 0 0 auto;

      padding:
        8px 10px 10px;

      background: #111419;

      border: 1px solid #4d535b;

      border-radius: 3px;

      box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, .025);
    }

    .physical-slot-header {
      display: flex;

      justify-content: space-between;

      gap: 28px;

      margin-bottom: 9px;

      color: #9ea5ad;

      font-size: 8px;

      letter-spacing: .04em;

      text-transform: uppercase;
    }


    /* =========================================================
         * PORT AREA
         * ========================================================= */

    .physical-port-scroll {
      overflow: visible;

      padding:
        13px 3px 13px;
    }

    /*
         * Bukan CSS grid fixed-width lagi.
         *
         * Sekarang setiap pasangan port menjadi satu kolom FLEX.
         * Jadi kalau QSFP lebih lebar, kolom otomatis ikut melebar.
         */
    .physical-port-grid {
      display: flex;

      align-items: flex-start;

      gap: 5px;

      width: max-content;
      min-width: max-content;
    }

    .physical-port-pair {
      display: flex;

      flex: 0 0 auto;
      flex-direction: column;

      align-items: center;

      gap: 7px;
    }

    .physical-port-position {
      display: flex;

      align-items: center;
      justify-content: center;

      min-height: 40px;
    }

    .physical-port-empty {
      width: 48px;
      height: 38px;

      visibility: hidden;
    }


    /* =========================================================
         * PORT BASE
         * ========================================================= */

    .physical-port {
      position: relative;

      flex: 0 0 auto;

      height: 38px;

      cursor: help;

      transition:
        transform .14s ease,
        filter .14s ease;
    }

    .physical-port:hover {
      z-index: 10;

      transform: scale(1.06);

      filter: brightness(1.08);
    }


    /* =========================================================
         * PORT LABEL
         * ========================================================= */

    .physical-port-label {
      position: absolute;

      left: 50%;

      z-index: 10;

      transform: translateX(-50%);

      color: #d8dde2;

      font-size: 8px;
      font-weight: 700;

      line-height: 1;

      white-space: nowrap;

      pointer-events: none;
    }

    .position-top .physical-port-label {
      top: -11px;
    }

    .position-bottom .physical-port-label {
      bottom: -11px;
    }


    /* =========================================================
         * COMMON PORT CAGE
         * ========================================================= */

    .physical-port-cage {
      position: absolute;

      inset: 0;

      display: block;

      padding: 4px;

      background:
        linear-gradient(180deg,
          #e6e8e9 0%,
          #b9bdc0 48%,
          #8f9498 100%);

      border: 1px solid #6e7479;

      border-radius: 1px;

      box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, .65),
        0 1px 2px rgba(0, 0, 0, .75);
    }

    .physical-port-core {
      position: absolute;

      display: block;

      background: #575b5f;

      border: 2px solid #17191c;

      box-shadow:
        inset 0 1px 1px rgba(255, 255, 255, .12);
    }

    .physical-port-led {
      position: absolute;

      z-index: 5;

      width: 4px;
      height: 4px;

      background: #555b61;

      border: 1px solid #17191c;

      border-radius: 50%;
    }


    /* =========================================================
         * RJ45 / UTP
         *
         * GE
         * ========================================================= */

    .port-rj45 {
      width: 48px;
    }

    .port-rj45 .physical-port-cage {
      padding: 4px;
    }

    /*
         * RJ45 socket:
         *
         * ┌────────────┐
         * │            │
         * │            │
         * └──┐      ┌──┘
         *    └──────┘
         */
    .port-rj45 .physical-port-core {
      inset:
        4px 4px 4px 4px;

      clip-path: polygon(0 0,
          100% 0,

          100% 70%,

          86% 70%,
          86% 84%,

          68% 84%,
          68% 100%,

          32% 100%,
          32% 84%,

          14% 84%,
          14% 70%,

          0 70%);
    }

    .port-rj45 .physical-port-led {
      top: 3px;
      right: 3px;
    }


    /* =========================================================
         * SFP / SFP+
         *
         * XGE
         * ========================================================= */

    .port-sfp {
      width: 48px;
    }

    .port-sfp .physical-port-cage {
      background:
        linear-gradient(180deg,
          #e7e8e9 0%,
          #c0c3c5 48%,
          #969a9d 100%);
    }

    .port-sfp .physical-port-core {
      inset:
        4px 4px 4px 4px;

      clip-path: none;
    }

    .port-sfp .physical-port-led {
      top: 3px;
      right: 3px;
    }


    /* =========================================================
         * QSFP
         *
         * FGE / HGE
         *
         * SAMA DENGAN SFP
         * TAPI LEBIH LEBAR
         * ========================================================= */

    .port-qsfp {
      width: 76px;
    }

    .port-qsfp .physical-port-cage {
      background:
        linear-gradient(180deg,
          #e7e8e9 0%,
          #c0c3c5 48%,
          #969a9d 100%);
    }

    .port-qsfp .physical-port-core {
      inset:
        4px 4px 4px 4px;

      clip-path: none;
    }

    .port-qsfp .physical-port-led {
      top: 3px;
      right: 3px;
    }


    /* =========================================================
         * STATUS: UP
         *
         * Seperti gambar referensi:
         * isi port menjadi HIJAU.
         * ========================================================= */

    .port-status-up .physical-port-core {
      background: #22c95a;

      border-color: #111713;

      box-shadow:
        inset 0 1px 2px rgba(255, 255, 255, .22);
    }

    .port-status-up .physical-port-led {
      background: #82ff9c;

      border-color: #16933e;

      box-shadow:
        0 0 5px rgba(100, 255, 137, .9);
    }


    /* =========================================================
         * STATUS: DOWN
         * ========================================================= */

    .port-status-down .physical-port-core {
      background: #57595b;
    }

    .port-status-down .physical-port-led {
      background: #4c5054;
    }


    /* =========================================================
         * STATUS: DISABLED
         * ========================================================= */

    .port-status-disabled .physical-port-core {
      background:
        repeating-linear-gradient(135deg,
          #595d61 0,
          #595d61 4px,
          #d1d3d5 4px,
          #d1d3d5 7px);
    }

    .port-status-disabled .physical-port-led {
      background: #8b9095;
    }


    /* =========================================================
         * RIGHT SIDE
         * ========================================================= */

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


    /* =========================================================
         * MEMBER SUMMARY
         * ========================================================= */

    .switch-member-summary {
      display: flex;

      justify-content: flex-end;

      gap: 14px;

      padding:
        7px 5px 0;

      color: #747c86;

      font-size: .72rem;
    }


    /* =========================================================
         * SUMMARY CARDS
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
         * SCROLL BAR
         * ========================================================= */

    .switch-slot-scroll::-webkit-scrollbar {
      height: 7px;
    }

    .switch-slot-scroll::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, .05);

      border-radius: 10px;
    }

    .switch-slot-scroll::-webkit-scrollbar-thumb {
      background: #545b63;

      border-radius: 10px;
    }

    .switch-slot-scroll::-webkit-scrollbar-thumb:hover {
      background: #69717a;
    }


    /* =========================================================
         * RESPONSIVE
         * ========================================================= */

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
