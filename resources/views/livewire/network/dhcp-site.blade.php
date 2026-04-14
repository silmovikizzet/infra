<div class="p-3">
  <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">All DHCP</h4>
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
      <select class="form-select form-select-sm text-uppercase" style="min-width: 200px" wire:model.live="site">
        <option value="">All Sites</option>
        @foreach ($siteOptions as $opt)
        <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
        @endforeach
      </select>

      <button class="btn btn-primary btn-sm" style="min-width: 120px;" wire:click="openCreate">
        + New DHCP
      </button>
    </div>
  </div>

  {{-- Flash message --}}
  @if (session('message'))
  <div class="alert alert-success mb-3" role="alert">
    {{ session('message') }}
  </div>
  @endif

  <div class="card">
    <h5 class="card-header">DHCP List</h5>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 vlan-table">
        <thead>
          <tr>
            <th>Site</th>
            <th>Pool Name</th>
            <th>Network</th>
            <th>Netmask</th>
            <th>DNS</th>
            <th>Gateway</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody class="table-border-bottom-0">
          @forelse($rows as $i => $r)
          <tr>
            <td>{{ strtoupper($r->site) }}</td>
            <td> <a class="text-primary" href="{{ url('network/dhcp/' . $r->id) }}">
                {{ $r->name }}
              </a></td>
            <td>{{ $r->network }}</td>
            <td>{{ $r->netmask ?? '-' }}</td>
            <td>
              @php $dns = is_array($r->dns_list) ? $r->dns_list : []; @endphp
              {{ !empty($dns) ? implode(', ', $dns) : '-' }}
            </td>
            <td>
              @php $gw = is_array($r->gateway_list) ? $r->gateway_list : []; @endphp
              {{ !empty($gw) ? implode(', ', $gw) : '-' }}
            </td>
            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <i class="icon-base bx bx-dots-vertical-rounded"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">
                  <a class="dropdown-item" href="{{ route('network.dhcp.id', ['dhcpPoolId' => $r->id]) }}">
                    <i class="icon-base bx bx-search me-2"></i> Check
                  </a>

                  @if (auth()->check() && auth()->user()->role === 'Admin')
                  <button class="dropdown-item" type="button" wire:click="edit({{ $r->id }})">
                    <i class="icon-base bx bx-edit-alt me-2"></i> Edit
                  </button>

                  <button class="dropdown-item text-danger" type="button" wire:click="delete({{ $r->id }})"
                    wire:confirm="Yakin hapus DHCP ini?">
                    <i class="icon-base bx bx-trash me-2"></i> Delete
                  </button>
                  @endif
                </div>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="11" class="text-center text-muted py-4">
              Data kosong.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div wire:ignore.self class="modal fade" id="dhcpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ $isEdit ? 'Edit DHCP' : 'Create DHCP' }}</h5>
        </div>

        <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Site</label>
                <select class="form-select" wire:model.defer="site">
                  <option value="">- Pilih site -</option>
                  @foreach ($siteOptions as $opt)
                  <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                  @endforeach
                </select>
                @error('site')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Nama Pool</label>
                <input type="text" class="form-control" wire:model.defer="name" placeholder="Contoh: AP-MHG">
                @error('name')
                <div class="text-danger small">{{ $message }}</div>
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
                <input type="text" class="form-control" wire:model.live.debounce.300ms="netmask"
                  placeholder="255.255.255.0">
                @error('netmask') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">DNS List (pisahkan spasi)</label>
                <input type="text" class="form-control" wire:model.defer="dns_list_text"
                  placeholder="10.2.0.251 172.16.0.252">
                @error('dns_list_text')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Gateway List (pisahkan spasi)</label>
                <input type="text" class="form-control" wire:model.defer="gateway_list_text" placeholder="10.2.87.254">
                @error('gateway_list_text')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
                <label class="form-label">Expired / Lease (d h m s)</label>
                <div class="row g-2">
                  <div class="col-md-3">
                    <input type="number" min="0" class="form-control" wire:model.defer="lease_days" placeholder="Hari">
                    @error('lease_days')
                    <div class="text-danger small">{{ $message }}</div>
                    @enderror
                  </div>
                  <div class="col-md-3">
                    <input type="number" min="0" max="23" class="form-control" wire:model.defer="lease_hours"
                      placeholder="Jam">
                    @error('lease_hours')
                    <div class="text-danger small">{{ $message }}</div>
                    @enderror
                  </div>
                  <div class="col-md-3">
                    <input type="number" min="0" max="59" class="form-control" wire:model.defer="lease_minutes"
                      placeholder="Menit">
                    @error('lease_minutes')
                    <div class="text-danger small">{{ $message }}</div>
                    @enderror
                  </div>
                  <div class="col-md-3">
                    <input type="number" min="0" max="59" class="form-control" wire:model.defer="lease_seconds"
                      placeholder="Detik">
                    @error('lease_seconds')
                    <div class="text-danger small">{{ $message }}</div>
                    @enderror
                  </div>
                </div>
                <div class="small text-muted mt-1">Contoh “expired 0 8 0 0” = 0 hari, 8 jam, 0 menit, 0 detik.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Remark</label>
                <textarea class="form-control" rows="3" wire:model.defer="remark" placeholder="Opsional..."></textarea>
                @error('remark')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
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
</div>
<script>
  document.addEventListener('livewire:init', () => {
      const dhcpEl = document.getElementById('dhcpModal');

      const dhcpModal = dhcpEl ? new bootstrap.Modal(dhcpEl) : null;

      // gunakan dispatch dari component: $this->dispatch('vlan-modal:open') dll
      Livewire.on('dhcp-pool-modal:open', () => dhcpModal?.show());
      Livewire.on('dhcp-pool-modal:close', () => dhcpModal?.hide());
    });
</script>
