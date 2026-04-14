<div class="p-3">
  <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">Credential Management</h4>
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

      {{-- Type Filter --}}
      <select class="form-select form-select-sm" style="min-width: 200px" wire:model.live="filterType">
        <option value="">All Types</option>
        @foreach ($typeOptions as $opt)
        <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
        @endforeach
      </select>

      <button class="btn btn-primary btn-sm" style="min-width: 120px;" wire:click="openCreate">
        + New Cred
      </button>
    </div>
  </div>

  <div class="card">
    <h5 class="card-header">Credential List</h5>

    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Port</th>
            <th>Type</th>
            <th>Password</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody class="table-border-bottom-0">
          @forelse($credentials as $c)
          <tr>
            <td>
              <div class="fw-semibold">{{ $c->name }}</div>
            </td>

            <td>{{ $c->username }}</td>

            <td>
              {{ $c->port ?: '-' }}
            </td>

            <td>
              <span class="badge bg-label-primary text-uppercase">
                {{ $c->type ?: '-' }}
              </span>
            </td>

            <td>
              {{-- jangan tampilkan password asli di tabel --}}
              @if (!empty($c->password))
              <span class="text-muted">••••••••</span>
              @else
              <span class="text-muted">-</span>
              @endif
            </td>

            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <i class="icon-base bx bx-dots-vertical-rounded"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">
                  <button class="dropdown-item" type="button" wire:click="edit({{ $c->id }})">
                    <i class="icon-base bx bx-edit-alt me-2"></i> Edit
                  </button>

                  <button class="dropdown-item text-danger" type="button" wire:click="delete({{ $c->id }})"
                    wire:confirm="Yakin hapus credential ini?">
                    <i class="icon-base bx bx-trash me-2"></i> Delete
                  </button>
                </div>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="7">
              <div class="p-4 text-center text-muted">
                No credential data
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $credentials->links() }}
    </div>
  </div>

  {{-- Modal --}}
  <div wire:ignore.self class="modal fade" id="credentialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">{{ $isEdit ? 'Edit Credential' : 'Create Credential' }}</h5>
        </div>

        <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Name</label>
                <input type="text" class="form-control" wire:model.defer="name" placeholder="Contoh: Router Utama">
                @error('name')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Type</label>
                <select class="form-select" wire:model.defer="type">
                  @foreach ($typeOptions as $opt)
                  <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                  @endforeach
                </select>
                @error('type')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" wire:model.defer="username" placeholder="Masukkan username">
                @error('username')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Port</label>
                <input type="number" class="form-control" wire:model.defer="port" placeholder="Contoh: 22" min="1"
                  max="65535">
                @error('port')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">
                  Password
                  @if($isEdit)
                  <span class="text-muted small">(kosongkan jika tidak ingin mengubah)</span>
                  @endif
                </label>
                <input type="password" class="form-control" wire:model.defer="password"
                  placeholder="{{ $isEdit ? 'Ketik password baru' : 'Masukkan password' }}">
                @error('password')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" class="form-control" wire:model.defer="password_confirmation"
                  placeholder="Ulangi password">
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
        const el = document.getElementById('credentialModal');
        const modal = el ? new bootstrap.Modal(el) : null;

        Livewire.on('credential-modal:open', () => modal?.show());
        Livewire.on('credential-modal:close', () => modal?.hide());
    });
</script>
