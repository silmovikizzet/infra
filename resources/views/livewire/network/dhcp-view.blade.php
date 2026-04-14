<div class="p-3">
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">DHCP Pool Detail</h4>
      <div class="text-muted small">
        Detail DHCP pool lokal + hasil pengecekan langsung via SSH
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('network.dhcp') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bx bx-arrow-back me-1"></i> Back
      </a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Data Lokal DHCP Pool</h5>

          <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEditModal">
            <i class="bx bx-pencil"></i>
          </button>
        </div>

        <div class="card-body">
          @if ($dhcpPool)
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <th style="width: 180px;">Site</th>
                  <td>{{ strtoupper($dhcpPool->site) ?: '-' }}</td>
                </tr>
                <tr>
                  <th>Pool Name</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceDhcpLoaded && $this->isPoolNameMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ $dhcpPool->name ?: '-' }}
                      </span>

                      @if ($deviceDhcpLoaded && $this->isPoolNameMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="Pool name database berbeda dengan pool name perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Network</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceDhcpLoaded && $this->isNetworkMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ $dhcpPool->network ?: '-' }}
                      </span>

                      @if ($deviceDhcpLoaded && $this->isNetworkMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="Network database berbeda dengan network perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Netmask</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceDhcpLoaded && $this->isNetmaskMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ $dhcpPool->netmask ?: '-' }}
                      </span>

                      @if ($deviceDhcpLoaded && $this->isNetmaskMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="Netmask database berbeda dengan netmask perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Gateway</th>
                  <td>
                    @php
                    $gateways = is_array($dhcpPool->gateway_list) ? $dhcpPool->gateway_list : [];
                    @endphp

                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceDhcpLoaded && $this->isGatewayMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ count($gateways) ? implode(', ', $gateways) : '-' }}
                      </span>

                      @if ($deviceDhcpLoaded && $this->isGatewayMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="Gateway database berbeda dengan gateway-list perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>DNS</th>
                  <td>
                    @php
                    $dnsList = is_array($dhcpPool->dns_list) ? $dhcpPool->dns_list : [];
                    @endphp

                    <div class="d-flex align-items-center gap-2">
                      <span class="{{ $deviceDhcpLoaded && $this->isDnsMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ count($dnsList) ? implode(', ', $dnsList) : '-' }}
                      </span>

                      @if ($deviceDhcpLoaded && $this->isDnsMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="DNS database berbeda dengan dns-list perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Lease Seconds</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceDhcpLoaded && $this->isLeaseMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ $dhcpPool->lease_seconds ?: '-' }}
                      </span>

                      @if ($deviceDhcpLoaded && $this->isLeaseMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="Lease seconds database berbeda dengan expired perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Options</th>
                  <td>
                    @php
                    $options = is_array($dhcpPool->options) ? $dhcpPool->options : [];
                    @endphp

                    <div class="d-flex align-items-start gap-2">
                      <div
                        class="{{ $deviceDhcpLoaded && $this->isOptionsMismatch() ? 'text-warning fw-semibold' : '' }}">
                        @if (count($options))
                        <div class="d-flex flex-column gap-1">
                          @foreach ($options as $opt)
                          <div>
                            <span class="badge bg-label-secondary">Option {{ $opt['code'] ?? '-' }}</span>
                            <span class="ms-1">{{ $opt['value'] ?? '-' }}</span>
                          </div>
                          @endforeach
                        </div>
                        @else
                        -
                        @endif
                      </div>

                      @if ($deviceDhcpLoaded && $this->isOptionsMismatch())
                      <i class="bx bx-error-circle text-warning mt-1" data-bs-toggle="tooltip"
                        title="Options database berbeda dengan options perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Description</th>
                  <td>{{ $dhcpPool->remark ?: '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          @else
          <div class="text-muted">Data DHCP pool tidak ditemukan.</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Data Perangkat DHCP Pool</h5>

          <button class="btn btn-outline-primary btn-sm" wire:click="refreshDeviceDhcpPool" wire:loading.attr="disabled"
            wire:target="refreshDeviceDhcpPool">
            <span wire:loading.remove wire:target="refreshDeviceDhcpPool">
              <i class="bx bx-refresh me-1"></i> Refresh
            </span>
            <span wire:loading wire:target="refreshDeviceDhcpPool">
              Processing...
            </span>
          </button>
        </div>

        <div class="card-body">
          @if ($deviceDhcpError !== '')
          <div class="alert alert-danger">
            {{ $deviceDhcpError }}
          </div>
          @endif

          @if ($isLoadingDeviceDhcp)
          <div class="text-muted">Mengambil data DHCP pool dari perangkat...</div>
          @elseif ($deviceDhcpLoaded)
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <th style="width: 180px;">Site</th>
                  <td>{{ strtoupper($dhcpPool->site) ?: '-' }}</td>
                </tr>
                <tr>
                  <th style="width: 180px;">Pool Name</th>
                  <td>{{ $deviceDhcpInfo['pool_name'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Network</th>
                  <td>{{ $deviceDhcpInfo['network'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Netmask</th>
                  <td>{{ $deviceDhcpInfo['mask'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Gateway</th>
                  <td>
                    {{ !empty($deviceDhcpInfo['gateway_list']) ? implode(', ', $deviceDhcpInfo['gateway_list']) : '-' }}
                  </td>
                </tr>
                <tr>
                  <th>DNS</th>
                  <td>
                    {{ !empty($deviceDhcpInfo['dns_list']) ? implode(', ', $deviceDhcpInfo['dns_list']) : '-' }}
                  </td>
                </tr>
                <tr>
                  <th>Lease</th>
                  <td>{{ $deviceDhcpInfo['lease'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Options</th>
                  <td>
                    @if (!empty($deviceDhcpInfo['options']))
                    <div class="d-flex flex-column gap-1">
                      @foreach ($deviceDhcpInfo['options'] as $opt)
                      <div>
                        <span class="badge bg-label-secondary">Option {{ $opt['code'] ?? '-' }}</span>
                        <span class="ms-1">{{ $opt['value'] ?? '-' }}</span>
                      </div>
                      @endforeach
                    </div>
                    @else
                    -
                    @endif
                  </td>
                </tr>
                <tr>
                  <th>Description</th>
                  <td>{{ $deviceDhcpInfo['description'] ?? '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          @else
          <div class="text-muted">Belum ada data DHCP pool dari perangkat.</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card h-100 min-w-0">
        <div class="card-header">
          <h5 class="mb-0">Switch Core & Credential</h5>
        </div>

        <div class="card-body">
          @if ($credential)
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Switch Core</label>
              <div class="fw-semibold">{{ $switch?->hostname ?: '-' }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Switch IP</label>
              <div class="fw-semibold">{{ $switch?->ip_address ?: '-' }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Location</label>
              <div class="fw-semibold">{{ $switch?->location ?: '-' }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Group</label>
              <div class="fw-semibold">{{ $switch?->group ?: '-' }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Credential Username</label>
              <div class="fw-semibold">{{ $credential->username ?: '-' }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">SSH Port</label>
              <div class="fw-semibold">{{ $credential->port ?: 22 }}</div>
            </div>

            <div class="col-12">
              <label class="form-label text-muted small mb-1">Execute Command</label>

              <div class="d-flex flex-wrap gap-2">
                <select class="form-select" style="max-width: 420px;" wire:model.live="selectedCommand">
                  @foreach ($commandOptions as $key => $option)
                  <option value="{{ $key }}">{{ $option['label'] }}</option>
                  @endforeach
                </select>

                <button class="btn btn-primary btn-sm" wire:click="refreshRemote" wire:loading.attr="disabled"
                  wire:target="refreshRemote">
                  <span wire:loading.remove wire:target="refreshRemote">
                    <i class="bx bx-refresh me-1"></i> Ambil dari Switch
                  </span>
                  <span wire:loading wire:target="refreshRemote">
                    Processing...
                  </span>
                </button>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label text-muted small mb-1">Command Preview</label>
              <pre class="bg-light border rounded p-3 small mb-0"
                style="white-space: pre-wrap;">{{ $commandPreview }}</pre>
            </div>
          </div>
          @else
          <div class="alert alert-warning mb-0">
            Credential untuk switch group core belum ditemukan.
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3 min-w-0">
    <div class="card-header">
      <h5 class="mb-0">VLAN yang Menggunakan DHCP Pool Ini</h5>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th class="ps-3">Name</th>
              <th>VLAN ID</th>
              <th>Site</th>
              <th>Network</th>
              <th>Gateway</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($dhcpPool->vlans as $vlan)
            <tr>
              <td class="ps-3">{{ $vlan->name ?: '-' }}</td>
              <td>{{ $vlan->vlan_id ?: '-' }}</td>
              <td>{{ $vlan->site ?: '-' }}</td>
              <td>{{ $vlan->network ?: '-' }}</td>
              <td>{{ $vlan->gateway ?: '-' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mt-3 min-w-0">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Hasil SSH dari Switch</h5>
      @if ($remoteLoaded)
      <span class="badge bg-label-success">Loaded</span>
      @endif
    </div>

    <div class="card-body">
      @if ($sshError !== '')
      <div class="alert alert-danger">
        {{ $sshError }}
      </div>
      @endif

      @if ($isLoadingRemote)
      <div class="text-muted">
        Mengambil data dari switch...
      </div>
      @elseif ($sshOutput !== '')
      <div class="overflow-auto">
        <pre class="bg-dark text-light rounded p-3 mb-0"
          style="white-space: pre-wrap; word-break: break-word; min-height: 240px;">{{ $sshOutput }}</pre>
      </div>
      @else
      <div class="text-muted">
        Belum ada data remote. Klik <b>Ambil dari Switch</b> untuk melihat statistik DHCP pool via SSH.
      </div>
      @endif
    </div>
  </div>
  @if ($showEditModal)
  <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.45);" aria-modal="true"
    role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit DHCP Pool</h5>
          <button type="button" class="btn-close" wire:click="closeEditModal"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Site</label>
              <select class="form-select @error('edit_site') is-invalid @enderror" wire:model.defer="edit_site">
                <option value="">- Pilih Site -</option>
                @foreach ($siteOptions as $site)
                <option value="{{ $site }}">{{ strtoupper($site) }}</option>
                @endforeach
              </select>
              @error('edit_site') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Pool Name</label>
              <input type="text" class="form-control @error('edit_name') is-invalid @enderror"
                wire:model.defer="edit_name">
              @error('edit_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Network</label>
              <input type="text" class="form-control @error('edit_network') is-invalid @enderror"
                wire:model.live="edit_network">
              @error('edit_network') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Netmask</label>
              <input type="text" class="form-control @error('edit_netmask') is-invalid @enderror"
                wire:model.live="edit_netmask">
              @error('edit_netmask') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Gateway List</label>
              <textarea class="form-control" rows="3" wire:model.defer="edit_gateway_text"
                placeholder="10.2.17.254, 10.2.17.253"></textarea>
              <div class="form-text">Pisahkan dengan koma atau baris baru.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">DNS List</label>
              <textarea class="form-control" rows="3" wire:model.defer="edit_dns_text"
                placeholder="8.8.8.8, 1.1.1.1"></textarea>
              <div class="form-text">Pisahkan dengan koma atau baris baru.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Lease Seconds</label>
              <input type="number" min="0" class="form-control @error('edit_lease_seconds') is-invalid @enderror"
                wire:model.defer="edit_lease_seconds">
              @error('edit_lease_seconds') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-12">
              <label class="form-label">Options</label>
              <textarea class="form-control" rows="5" wire:model.defer="edit_options_text"
                placeholder="option 43 ip-address 192.168.200.122 192.168.200.121&#10;option 66 ascii tftp.local&#10;option 67 ascii bootfile.bin"></textarea>
              <div class="form-text">Format per baris mengikuti command switch, mis.
                <code>option 43 ip-address 192.168.200.122 192.168.200.121</code>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control @error('edit_remark') is-invalid @enderror" rows="3"
                wire:model.defer="edit_remark"></textarea>
              @error('edit_remark') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" wire:click="closeEditModal">
            Cancel
          </button>

          <button type="button" class="btn btn-primary" wire:click="saveEdit" wire:loading.attr="disabled"
            wire:target="saveEdit">
            <span wire:loading.remove wire:target="saveEdit">Save</span>
            <span wire:loading wire:target="saveEdit">Saving...</span>
          </button>
        </div>
      </div>
    </div>
  </div>
  @endif
</div>
