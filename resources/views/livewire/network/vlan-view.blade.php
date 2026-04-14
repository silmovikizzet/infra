<div class="p-3">
  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">VLAN Detail</h4>
      <div class="text-muted small">
        Detail VLAN lokal + hasil pengecekan langsung via SSH
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('network.vlan') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bx bx-arrow-back me-1"></i> Back
      </a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Data Lokal VLAN</h5>

          <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openEditModal">
            <i class="bx bx-pencil"></i>
          </button>
        </div>

        <div class="card-body">
          @if ($vlan)
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <th style="width: 180px;">Site</th>
                  <td>{{ strtoupper($vlan->site) ?: '-' }}</td>
                </tr>
                <tr>
                  <th>VLAN Name</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceVlanLoaded && $this->isVlanNameMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ $vlan->name ?: '-' }}
                      </span>

                      @if ($deviceVlanLoaded && $this->isVlanNameMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="VLAN name database berbeda dengan description perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>VLAN ID</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="badge {{ $deviceVlanLoaded && $this->isVlanIdMismatch() ? 'bg-label-warning text-warning fw-semibold' : 'bg-label-primary' }}">
                        {{ $vlan->vlan_id ?: '-' }}
                      </span>

                      @if ($deviceVlanLoaded && $this->isVlanIdMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="VLAN ID database berbeda dengan VLAN ID perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Network</th>
                  <td>{{ $networkInfo['network'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Subnet Mask</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceVlanLoaded && $this->isSubnetMaskMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ $networkInfo['subnet_mask'] ?? '-' }}
                      </span>

                      @if ($deviceVlanLoaded && $this->isSubnetMaskMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="Subnet mask database berbeda dengan subnet mask perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>
                <tr>
                  <th>Gateway</th>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span
                        class="{{ $deviceVlanLoaded && $this->isGatewayMismatch() ? 'text-warning fw-semibold' : '' }}">
                        {{ $networkInfo['gateway'] ?? '-' }}
                      </span>

                      @if ($deviceVlanLoaded && $this->isGatewayMismatch())
                      <i class="bx bx-error-circle text-warning" data-bs-toggle="tooltip"
                        title="Gateway database berbeda dengan IPv4 address perangkat."></i>
                      @endif
                    </div>
                  </td>
                </tr>

                <tr>
                  <th>Usable IP Range</th>
                  <td>{{ $networkInfo['range_ip'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Usable Hosts</th>
                  <td>{{ $networkInfo['usable_hosts'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Description</th>
                  <td>{{ $vlan->remark ?: '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          @else
          <div class="text-muted">Data VLAN tidak ditemukan.</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Data Perangkat VLAN</h5>

          <button class="btn btn-outline-primary btn-sm" wire:click="refreshDeviceVlan" wire:loading.attr="disabled"
            wire:target="refreshDeviceVlan">
            <span wire:loading.remove wire:target="refreshDeviceVlan">
              <i class="bx bx-refresh me-1"></i> Refresh
            </span>
            <span wire:loading wire:target="refreshDeviceVlan">
              Processing...
            </span>
          </button>
        </div>

        <div class="card-body">
          @if ($deviceVlanError !== '')
          <div class="alert alert-danger">
            {{ $deviceVlanError }}
          </div>
          @endif

          @if ($isLoadingDeviceVlan)
          <div class="text-muted">Mengambil data VLAN dari perangkat...</div>
          @elseif ($deviceVlanLoaded)
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <th style="width: 180px;">Site</th>
                  <td>{{ strtoupper($vlan->site) ?: '-' }}</td>
                </tr>
                <tr>
                  <th>VLAN Name</th>
                  <td>{{ $deviceInfo['vlan_name'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th style="width: 180px;">VLAN ID</th>
                  <td>
                    <span class="badge bg-label-primary">{{ $deviceInfo['vlan_id'] ?? '-' }}</span>
                  </td>
                </tr>
                <tr>
                  <th>Gateway</th>
                  <td>{{ $deviceInfo['ipv4_address'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Subnet Mask</th>
                  <td>{{ $deviceInfo['subnet_mask'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Type</th>
                  <td>{{ $deviceInfo['type'] ?? '-' }}</td>
                </tr>
                <tr>
                  <th>Description</th>
                  <td>{{ $deviceInfo['description'] ?? '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          @else
          <div class="text-muted">Belum ada data VLAN dari perangkat.</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card h-100 min-w-0">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
          <div>
            <h5 class="mb-0">Credential & Command</h5>
          </div>
        </div>

        <div class="card-body">
          @if ($credential)
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Credential Name / Host</label>
              <div class="fw-semibold">{{ $routerHost !== '' ? $routerHost : '-' }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Username</label>
              <div class="fw-semibold">{{ $credential->username ?: '-' }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Port</label>
              <div class="fw-semibold">{{ $credential->port ?: 22 }}</div>
            </div>

            <div class="col-md-6">
              <label class="form-label text-muted small mb-1">Type</label>
              <div>
                <span class="badge bg-label-secondary text-uppercase">
                  {{ $credential->type ?: '-' }}
                </span>
              </div>
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
                    <i class="bx bx-refresh me-1"></i> Ambil dari Router
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
            Credential belum terhubung ke data VLAN ini.
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card mt-3 min-w-0">
    <div class="card-header">
      <h5 class="mb-0">DHCP Pool yang digunakan VLAN Ini</h5>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th class="ps-3">Name</th>
              <th>Network</th>
              <th>Netmask</th>
              <th>DNS</th>
              <th>Gateway</th>
              <th>Lease Second</th>
            </tr>
          </thead>
          <tbody>
            @if ($vlan?->dhcpPool)
            <tr>
              <td class="ps-3">{{ $vlan->dhcpPool->name ?: '-' }}</td>
              <td>{{ $vlan->dhcpPool->network ?: '-' }}</td>
              <td>{{ $vlan->dhcpPool->netmask ?: '-' }}</td>
              <td>{{ is_array($vlan->dhcpPool->dns_list) ? implode(', ', $vlan->dhcpPool->dns_list) : '-' }}</td>
              <td>{{ is_array($vlan->dhcpPool->gateway_list) ? implode(', ', $vlan->dhcpPool->gateway_list) : '-' }}
              <td>{{ $vlan->dhcpPool->lease_seconds ?: '-' }}</td>
              </td>
            </tr>
            @else
            <tr>
              <td colspan="5" class="text-muted ps-3">DHCP Pool belum terhubung.</td>
            </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card mt-3 min-w-0">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Hasil SSH dari Router</h5>
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
        Mengambil data dari router...
      </div>
      @elseif ($sshOutput !== '')
      <div class="overflow-auto">
        <pre class="bg-dark text-light rounded p-3 mb-0"
          style="white-space: pre-wrap; word-break: break-word; min-height: 240px;">{{ $sshOutput }}</pre>
      </div>
      @else
      <div class="text-muted">
        Belum ada data remote. Klik <b>Ambil dari Router</b> untuk melihat detail VLAN via SSH.
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
          <h5 class="modal-title">Edit VLAN</h5>
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
              <label class="form-label">VLAN Name</label>
              <input type="text" class="form-control @error('edit_name') is-invalid @enderror"
                wire:model.defer="edit_name">
              @error('edit_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">VLAN ID</label>
              <input type="number" min="1" max="4094" class="form-control @error('edit_vlan_id') is-invalid @enderror"
                wire:model.defer="edit_vlan_id">
              @error('edit_vlan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
              <label class="form-label">Gateway</label>
              <input type="text" class="form-control @error('edit_gateway') is-invalid @enderror"
                wire:model.defer="edit_gateway">
              @error('edit_gateway') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
