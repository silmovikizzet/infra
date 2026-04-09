<div class="p-3">

  {{-- Header --}}

<div class="d-flex align-items-start justify-content-between gap-3 mb-3">
    <div>
        <h4 class="mb-1">All VLAN</h4>
        @if($site)
            <div class="text-muted small">Site: <span class="fw-semibold">{{ strtoupper($site) }}</span></div>
        @endif
    </div>

    <div class="d-flex gap-2 align-items-center">
        {{-- Search --}}
        <div class="input-group input-group-sm" style="min-width: 260px;">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <input type="text"
                   class="form-control"
                   placeholder="Search"
                   wire:model.live.debounce.400ms="search">
            @if($search !== '')
                <button class="btn btn-outline-secondary" type="button" wire:click="$set('search','')">
                    Clear
                </button>
            @endif
        </div>

        {{-- Site Filter --}}
        <select class="form-select form-select-sm text-uppercase" style="min-width: 200px"
                wire:model.live="site">
            <option value="">All Sites</option>
            @foreach($siteOptions as $opt)
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

  {{-- Error summary (optional) --}}
  @if ($errors->any())
    <div class="alert alert-danger mb-3" role="alert">
      <div class="fw-semibold mb-1">Oops! Please fix the fields below.</div>
      <ul class="mb-0 ps-3">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Table --}}
  <div class="card">
    <h5 class="card-header">VLAN List</h5>
    <div class="table-responsive">
<table class="table table-hover table-sm mb-0 vlan-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Network</th>
            <th>First IP</th>
            <th>Last IP</th>
            <th>Clients</th>
            <th>Gateway</th>
            <th>NetMask</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody class="table-border-bottom-0">
          @forelse($vlans as $vlan)
            <tr>
              <td class="fw-semibold">
                <a class="text-primary" href="{{ url('vlans-show/' . $vlan->id) }}">
                  {{ $vlan->vlan_id }}
                </a>

                @if($vlan->dhcp)
                  <span class="badge bg-label-success ms-2 font-monospace small" title="Pool: {{ $vlan->dhcp }}">
                    D
                  </span>
                @endif
              </td>

              <td  title="{{ $vlan->remark }}">
                <a class="text-body" href="{{ url('vlans-show/' . $vlan->id) }}">
                  {{ $vlan->nama }}
                </a>
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
                    <button class="dropdown-item" type="button" wire:click="check({{ $vlan->id }})">
                      <i class="icon-base bx bx-search me-2"></i> Check
                    </button>

                    @if(auth()->check() && auth()->user()->role === 'Admin')
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

  {{-- Create/Edit Modal (Bootstrap) --}}
  <div wire:ignore.self class="modal fade" id="vlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">{{ $isEdit ? 'Edit VLAN' : 'Create VLAN' }}</h5>
          <button type="button" class="btn-close" aria-label="Close" wire:click="closeModal"></button>
        </div>

        <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
          <div class="modal-body">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">VLAN ID</label>
                <input type="number" class="form-control" placeholder="e.g. 10" wire:model.defer="vlan_id">
                @error('vlan_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">VLAN Name</label>
                <input type="text" class="form-control" placeholder="e.g. Staff" wire:model.defer="nama">
                @error('nama') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Network (CIDR)</label>
                <input type="text" class="form-control" placeholder="10.1.1.0/24" wire:model.defer="network">
                <div class="form-text">Use CIDR format, e.g. 10.1.1.0/24</div>
                @error('network') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Gateway</label>
                <input type="text" class="form-control" placeholder="10.1.1.1" wire:model.defer="gateway">
                @error('gateway') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">DHCP Pool</label>
                <input type="text" class="form-control" placeholder="10.1.1.10-10.1.1.200" wire:model.defer="dhcp">
                @error('dhcp') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Remark</label>
                <input type="text" class="form-control" placeholder="Optional" wire:model.defer="remark">
                @error('remark') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
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
              Cancel
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
          <button type="button" class="btn-close" aria-label="Close" wire:click="$set('checkModal', false)"></button>
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
@push('scripts')
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
@endpush