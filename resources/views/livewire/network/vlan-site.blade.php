<div class="p-3">
  <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">All VLAN</h4>
    </div>

    <div class="d-flex gap-2 align-items-center">
      {{-- Search --}}
      <div class="input-group input-group-sm" style="min-width: 260px;">
        <span class="input-group-text"><i class="bx bx-search"></i></span>
        <input type="text" class="form-control" placeholder="Search" wire:model.live.debounce.400ms="search">
        @if ($search !== '')
        <button class="btn btn-outline-secondary" type="button" wire:click="$set('search','')">
          Clear
        </button>
        @endif
      </div>

      {{-- Site Filter --}}
      <select class="form-select form-select-sm text-uppercase" style="min-width: 200px" wire:model.live="filterSite">
        <option value="">All Sites</option>
        @foreach ($siteOptions as $opt)
        <option value="{{ $opt }}">{{ $opt }}</option>
        @endforeach
      </select>

      <button class="btn btn-primary btn-sm" style="min-width: 120px;" wire:click="openCreate">
        + New VLAN
      </button>
    </div>
  </div>

  {{-- Flash message --}}
  @if (session('message'))
  <div class="alert alert-success mb-3" role="alert">
    {{ session('message') }}
  </div>
  @endif

  {{-- Table --}}
  <div class="card">
    <h5 class="card-header">VLAN List</h5>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 vlan-table">
        <thead>
          <tr>
            <th>Site</th>
            <th>Name</th>
            <th>VLAN ID</th>
            <th>Network</th>
            <th>First IP</th>
            <th>Last IP</th>
            <th>Clients</th>
            <th>Gateway</th>
            <th>Subnet Mask</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody class="table-border-bottom-0">
          @forelse($vlans as $vlan)
          <tr>
            <td>{{ strtoupper($vlan->site) }}</td>

            <td title="{{ $vlan->remark }}">
              <a class="text-primary" href="{{ url('network/vlan/' . $vlan->id) }}">
                {{ $vlan->name }}
              </a>
              @if (!empty($vlan->dhcp_pool_id))
              <span class="badge bg-label-success ms-2 font-monospace small"
                title="DHCP Pool: {{ optional($vlan->dhcpPool)->name }} ({{ optional($vlan->dhcpPool)->network }})">
                D
              </span>
              @endif
            </td>

            <td class="fw-semibold">
              {{ $vlan->vlan_id }}
            </td>

            <td>{{ $vlan->network }}</td>
            <td>{{ $vlan->start_ip }}</td>
            <td>{{ $vlan->last_ip }}</td>
            <td>{{ $vlan->client }}</td>
            <td>{{ $vlan->gateway }}</td>
            <td>{{ $vlan->netmask }}</td>

            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <i class="icon-base bx bx-dots-vertical-rounded"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">
                  <a class="dropdown-item" href="{{ route('network.vlan.id', ['vlanId' => $vlan->id]) }}">
                    <i class="icon-base bx bx-search me-2"></i> Check
                  </a>

                  @if (auth()->check() && auth()->user()->role === 'Admin')
                  <button class="dropdown-item" type="button" wire:click="edit({{ $vlan->id }})">
                    <i class="icon-base bx bx-edit-alt me-2"></i> Edit
                  </button>

                  <button class="dropdown-item text-danger" type="button" wire:click="delete({{ $vlan->id }})"
                    wire:confirm="Yakin hapus VLAN ini?">
                    <i class="icon-base bx bx-trash me-2"></i> Delete
                  </button>
                  @endif
                </div>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="10">
              <div class="p-4 text-center text-muted">
                No VLAN data
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div wire:ignore.self class="modal fade" id="vlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">{{ $isEdit ? 'Edit VLAN' : 'Create VLAN' }}</h5>
        </div>

        <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
          <div class="modal-body">



            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Site</label>
                <select class="form-select" wire:model.live="site">
                  <option value="">- Pilih site -</option>
                  @foreach ($siteOptions as $opt)
                  <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                  @endforeach
                </select>
                @error('site') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">VLAN ID</label>
                <input type="number" class="form-control" placeholder="e.g. 10" wire:model.defer="vlan_id">
                @error('vlan_id')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">VLAN Name</label>
                <input type="text" class="form-control" placeholder="e.g. Staff" wire:model.defer="name">
                @error('name')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Network (CIDR / IP)</label>
                <input type="text" class="form-control" wire:model.live.debounce.300ms="network"
                  placeholder="10.2.86.0 atau 10.2.86.0/24">
                @error('network') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Netmask</label>
                <input type="text" class="form-control" placeholder="e.g. 255.255.255.0"
                  wire:model.live.debounce.300ms="netmask">
                @error('netmask') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>


              <div class="col-md-6">
                <label class="form-label">Gateway</label>
                <input type="text" class="form-control" placeholder="10.1.1.1" wire:model.defer="gateway">
                @error('gateway')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">DHCP Pool</label>
                <select class="form-select" wire:model.defer="dhcp_pool_id" @disabled(empty($site))>
                  <option value="" @selected(empty($dhcp_pool_id))>(Static / No DHCP)</option>
                  @foreach ($dhcpPoolOptions as $p)
                  <option value="{{ $p['id'] }}" @selected((int) $dhcp_pool_id===(int) $p['id'])>
                    {{ $p['label'] }}
                  </option>
                  @endforeach
                </select>


                @error('dhcp_pool_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Remark</label>
                <input type="text" class="form-control" placeholder="Optional" wire:model.defer="remark">
                @error('remark')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <div class="text-muted small">Clients</div>
                  <div class="fw-semibold">{{ $client ?? '-' }}</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <div class="text-muted small">Start IP</div>
                  <div class="fw-semibold">{{ $start_ip ?? '-' }}</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-3">
                  <div class="text-muted small">Last IP</div>
                  <div class="fw-semibold">{{ $last_ip ?? '-' }}</div>
                </div>
              </div>
            </div>

          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" wire:click="closeModal">
              Batal
            </button>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"
              wire:target="{{ $isEdit ? 'update' : 'store' }}">
              <span wire:loading.remove wire:target="{{ $isEdit ? 'update' : 'store' }}">
                {{ $isEdit ? 'Update' : 'Create' }}
              </span>
              <span wire:loading wire:target="{{ $isEdit ? 'update' : 'store' }}">
                Processing...
              </span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- ARP Check Modal (Bootstrap) --}}
  <div wire:ignore.self class="modal fade" id="arpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">ARP Table</h5>
        </div>

        <div class="modal-body">
          <pre class="bg-light p-3 rounded mb-0" style="max-height:60vh; overflow:auto;">
          {{ $arpResult }}
        </pre>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" wire:click="$set('checkModal', false)">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Bootstrap modal bridge for Livewire --}}
<script>
  document.addEventListener('livewire:init', () => {
    const vlanEl = document.getElementById('vlanModal');
    const arpEl = document.getElementById('arpModal');

    const vlanModal = vlanEl ? new bootstrap.Modal(vlanEl) : null;
    const arpModal = arpEl ? new bootstrap.Modal(arpEl) : null;

    // gunakan dispatch dari component: $this->dispatch('vlan-modal:open') dll
    Livewire.on('vlan-modal:open', () => vlanModal?.show());
    Livewire.on('vlan-modal:close', () => vlanModal?.hide());
    Livewire.on('arp-modal:open', () => arpModal?.show());
    Livewire.on('arp-modal:close', () => arpModal?.hide());
  });
</script>
