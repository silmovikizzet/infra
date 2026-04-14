<div class="p-3">
  <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">IP Address</h4>
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

      {{-- VLAN Filter (tergantung site) --}}
      <select class="form-select form-select-sm text-uppercase" style="min-width: 240px" wire:model.live="vlan_id"
        @disabled(empty($site))>
        <option value="">{{ empty($site) ? 'Pilih site dulu' : 'All VLAN' }}</option>
        @foreach ($vlanOptions as $opt)
        <option value="{{ $opt['id'] }}">{{ strtoupper($opt['label']) }}</option>
        @endforeach
      </select>

      <button class="btn btn-primary btn-sm" style="min-width: 140px;" wire:click="openCreate">
        + New IP
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
    <h5 class="card-header">IP Address List</h5>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 vlan-table">
        <thead>
          <tr>
            <th>Site</th>
            <th>IP</th>
            <th>VLAN</th>
            <th>Description</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody class="table-border-bottom-0">
          @forelse($rows as $r)
          <tr>
            <td class="text-uppercase">
              {{ strtoupper($r->vlan->site ?? '-') }}
            </td>

            <td class="fw-semibold">{{ $r->ip }}</td>

            <td class="text-uppercase">
              @php
              $v = $r->vlan; // dari database lewat relasi
              $vid = $v->vlan_id ?? null;
              $nm = $v->name ?? ($v->name ?? null);
              $label = $v ? ('['.($vid ?? ('#'.$v->id)).'] '.($nm ?? '')) : '-';
              @endphp

              <div>{{ strtoupper(trim($label)) }}</div>
            </td>
            <td>{{ $r->description }}</td>

            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <i class="icon-base bx bx-dots-vertical-rounded"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">
                  @if (auth()->check() && auth()->user()->role === 'Admin')
                  <button class="dropdown-item" type="button" wire:click="edit({{ $r->id }})">
                    <i class="icon-base bx bx-edit-alt me-2"></i> Edit
                  </button>

                  <button class="dropdown-item text-danger" type="button" wire:click="delete({{ $r->id }})"
                    wire:confirm="Yakin hapus IP ini?">
                    <i class="icon-base bx bx-trash me-2"></i> Delete
                  </button>
                  @endif
                </div>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              Data kosong.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Modal --}}
  <div wire:ignore.self class="modal fade" id="ipAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ $isEdit ? 'Edit IP Address' : 'New IP Address' }}</h5>
        </div>

        <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Site</label>
                <select class="form-select" wire:model.live="form_site">
                  <option value="">- Pilih site -</option>
                  @foreach ($siteOptions as $opt)
                  <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                  @endforeach
                </select>
                @error('form_site')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">IP Address</label>
                <input type="text" class="form-control" wire:model.defer="ip" placeholder="Contoh: 10.2.86.10">
                @error('ip')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Description</label>
                <input type="text" class="form-control" wire:model.defer="description"
                  placeholder="Contoh: IP OLT / Gateway / Management">
                @error('description')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12">
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
    const el = document.getElementById('ipAddressModal');
    const modal = el ? new bootstrap.Modal(el) : null;

    Livewire.on('ip-address-modal:open', () => modal?.show());
    Livewire.on('ip-address-modal:close', () => modal?.hide());
  });
</script>
