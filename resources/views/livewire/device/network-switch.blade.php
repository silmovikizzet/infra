<div class="container-fluid p-0">
  <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">Network Switch</h4>
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

      <select class="form-select form-select-sm text-uppercase" style="min-width: 180px" wire:model.live="filterGroup">
        <option value="">All Groups</option>
        @foreach ($groupOptions as $opt)
        <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
        @endforeach
      </select>

      <button class="btn btn-primary btn-sm" style="min-width: 120px;" wire:click="openCreate">
        + New Switch
      </button>
    </div>
  </div>

  {{-- Table --}}
  <div class="card">
    <h5 class="card-header">Switch List</h5>

    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead>
          @php
          $thBtn = "btn btn-link btn-sm p-0 text-decoration-none text-body fw-semibold";
          $sortIcon = function(string $field) use ($sortField, $sortDirection) {
          if ($sortField !== $field) return 'bx bx-sort';
          return $sortDirection === 'asc' ? 'bx bx-sort-up' : 'bx bx-sort-down';
          };
          @endphp

          <tr>
            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('location')">
                Site <i class="bx {{ $sortIcon('location') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('hostname')">
                Hostname <i class="bx {{ $sortIcon('hostname') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('ip_address')">
                IP <i class="bx {{ $sortIcon('ip_address') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('mac_address')">
                MAC <i class="bx {{ $sortIcon('mac_address') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('serial_number')">
                Serial <i class="bx {{ $sortIcon('serial_number') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('group')">
                Group <i class="bx {{ $sortIcon('group') }} ms-1"></i>
              </button>
            </th>

            <th style="min-width:150px;">
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('type')">
                Type <i class="bx {{ $sortIcon('type') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('floor')">
                Floor <i class="bx {{ $sortIcon('floor') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('tower')">
                Tower <i class="bx {{ $sortIcon('tower') }} ms-1"></i>
              </button>
            </th>

            <th style="min-width:130px;">
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('warranty')">
                Warranty <i class="bx {{ $sortIcon('warranty') }} ms-1"></i>
              </button>
            </th>

            <th>
              <button type="button" class="{{ $thBtn }}" wire:click="sortBy('firmware_version')">
                Firmware <i class="bx {{ $sortIcon('firmware_version') }} ms-1"></i>
              </button>
            </th>

            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody class="table-border-bottom-0">
          @forelse($assets as $a)
          <tr>
            <td class="text-uppercase">{{ $a->location }}</td>
            <td class="fw-semibold" title="{{ $a->remark }}">
              <a href="{{ route('device.switch.id', [
        'networkswitchId' => $a->id,
    ]) }}" class="text-primary text-decoration-none">
                {{ $a->hostname ?: '-' }}
              </a>
            </td>

            <td class="font-monospace">
              @if (!empty($a->ip_address))
              <a href="{{ route('device.switch.id', [
            'networkswitchId' => $a->id,
        ]) }}" class="text-primary text-decoration-none">
                {{ $a->ip_address }}
              </a>
              @else
              <span class="text-muted">-</span>
              @endif
            </td>
            <td class="font-monospace">{{ $a->mac_address ?? '-' }}</td>
            <td class="font-monospace">{{ $a->serial_number ?? '-' }}</td>
            <td>{{ $a->group ?? '-' }}</td>

            <td>{{ $a->type ?? '-' }}</td>
            <td>{{ $a->floor ?? '-' }}</td>
            <td>{{ $a->tower ?? '-' }}</td>
            <td>{{ $a->warranty ?? '-' }}</td>
            <td class="font-monospace">{{ $a->firmware_version ?? '-' }}</td>

            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <i class="icon-base bx bx-dots-vertical-rounded"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">
                  @if (auth()->check() && auth()->user()->role === 'Admin')
                  <button class="dropdown-item" type="button" wire:click="edit({{ $a->id }})">
                    <i class="icon-base bx bx-edit-alt me-2"></i> Edit
                  </button>

                  <button class="dropdown-item text-danger" type="button" wire:click="delete({{ $a->id }})"
                    wire:confirm="Yakin hapus Asset ini?">
                    <i class="icon-base bx bx-trash me-2"></i> Delete
                  </button>
                  @else
                  <div class="px-3 py-2 text-muted small">Hanya Admin yang bisa edit/hapus</div>
                  @endif
                </div>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="14">
              <div class="p-4 text-center text-muted">
                No Asset data
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  {{-- Pagination --}}
  <div class="d-flex justify-content-between align-items-center mt-3">

    <div>
      {{ $assets->links() }}
    </div>
  </div>

  {{-- Modal --}}
  <div wire:ignore.self class="modal fade" id="assetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">{{ $isEdit ? 'Edit Asset' : 'Create Asset' }}</h5>
        </div>

        <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
          <div class="modal-body">

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Site / Location</label>
                <select class="form-select" wire:model.live="location">
                  <option value="">- Pilih site -</option>
                  @foreach ($siteOptions as $opt)
                  <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                  @endforeach
                </select>
                @error('location') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">Hostname</label>
                <input type="text" class="form-control" wire:model.defer="hostname" placeholder="e.g. SW-CORE-01">
                @error('hostname') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">Group</label>
                <select class="form-select" wire:model.defer="group">
                  <option value="access">access</option>
                  <option value="distri">distri</option>
                  <option value="farm">farm</option>
                  <option value="core">core</option>
                </select>
                @error('group') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>


              <div class="col-md-4">
                <label class="form-label">Type</label>
                <input type="text" class="form-control" wire:model.defer="type"
                  placeholder="e.g. Huawei / Mikrotik / Cisco">
                @error('type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">IP Address</label>
                <input type="text" class="form-control font-monospace" wire:model.defer="ip_address"
                  placeholder="10.1.1.10">
                @error('ip_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">MAC Address</label>
                <input type="text" class="form-control font-monospace" wire:model.live.debounce.150ms="mac_address"
                  placeholder="AA:BB:CC:DD:EE:FF">
                @error('mac_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">Serial Number</label>
                <input type="text" class="form-control" wire:model.defer="serial_number" placeholder="Optional">
                @error('serial_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label class="form-label">Firmware Version</label>
                <input type="text" class="form-control font-monospace" wire:model.defer="firmware_version"
                  placeholder="Optional">
                @error('firmware_version') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">End of Support</label>
                <input type="date" class="form-control" wire:model.defer="end_of_support">
                @error('end_of_support') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Warranty</label>
                <input type="text" class="form-control" wire:model.defer="warranty">
                @error('warranty') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Floor</label>
                <input type="text" class="form-control" wire:model.defer="floor" placeholder="Optional">
                @error('floor') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Tower</label>
                <input type="text" class="form-control" wire:model.defer="tower" placeholder="Optional">
                @error('tower') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Credential</label>
                <select class="form-select" wire:model.defer="credential_id">
                  <option value="">(Tidak pakai credential)</option>
                  @foreach ($credentialOptions as $c)
                  <option value="{{ $c['id'] }}">
                    {{ $c['label'] }}
                  </option>
                  @endforeach
                </select>
                @error('credential_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Remark</label>
                <input type="text" class="form-control" wire:model.defer="remark" placeholder="Optional">
                @error('remark') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
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

{{-- Bootstrap modal bridge --}}
<script>
  document.addEventListener('livewire:init', () => {
        const el = document.getElementById('assetModal');
        const modal = el ? new bootstrap.Modal(el) : null;

        Livewire.on('asset-modal:open', () => modal?.show());
        Livewire.on('asset-modal:close', () => modal?.hide());
    });
</script>
