<div class="p-3">
  <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
    <div>
      <h4 class="mb-1">User Management</h4>
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

      {{-- Role Filter --}}
      <select class="form-select form-select-sm" style="min-width: 200px" wire:model.live="filterRole">
        <option value="">All Roles</option>
        @foreach ($roleOptions as $r)
        <option value="{{ $r }}">{{ $r }}</option>
        @endforeach
      </select>

      {{-- Site Filter --}}
      <select class="form-select form-select-sm text-uppercase" style="min-width: 100px" wire:model.live="filterSite">
        <option value="">All Sites</option>
        @foreach ($siteOptions as $opt)
        <option value="{{ $opt }}">{{ $opt }}</option>
        @endforeach
      </select>

      <button class="btn btn-primary btn-sm" style="min-width: 120px;" wire:click="openCreate">
        + New User
      </button>
    </div>
  </div>

  <div class="card">
    <h5 class="card-header">User List</h5>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Role</th>
            <th>Sites</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody class="table-border-bottom-0">
          @forelse($users as $u)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="rounded-circle bg-label-secondary d-inline-flex align-items-center justify-content-center"
                  style="width: 28px; height: 28px; overflow:hidden;">
                  @if (!empty($u->profile_image))
                  <img src="{{ asset('storage/' . $u->profile_image) }}" alt="avatar"
                    style="width:100%; height:100%; object-fit:cover;">
                  @else
                  <span class="fw-semibold">{{ strtoupper(substr($u->name ?? 'U', 0, 1)) }}</span>
                  @endif
                </div>

                <div class="lh-1">
                  <div class="fw-semibold">{{ $u->name }}</div>
                </div>
              </div>
            </td>

            <td>
              {{ $u->email ?? '-' }}
            </td>

            <td>
              @if ($u->role === 'Admin')
              <span class="badge bg-label-danger">Admin</span>
              @else
              <span class="badge bg-label-primary">{{ $u->role ?? '-' }}</span>
              @endif
            </td>

            <td style="max-width: 260px;">
              @php
              $siteList = $u->sites?->pluck('site')->map(fn($s)=>strtoupper($s))->toArray() ?? [];
              @endphp
              @if (empty($siteList))
              <span class="text-muted">-</span>
              @else
              <div class="d-flex flex-wrap gap-1">
                @foreach ($siteList as $s)
                <span class="badge bg-label-secondary">{{ $s }}</span>
                @endforeach
              </div>
              @endif
            </td>

            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"
                  aria-expanded="false">
                  <i class="icon-base bx bx-dots-vertical-rounded"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end">
                  <button class="dropdown-item" type="button" wire:click="edit({{ $u->id }})">
                    <i class="icon-base bx bx-edit-alt me-2"></i> Edit
                  </button>

                  <button class="dropdown-item text-danger" type="button" wire:click="delete({{ $u->id }})"
                    wire:confirm="Yakin hapus user ini?">
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
                No user data
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $users->links() }}
    </div>
  </div>

  {{-- Modal --}}
  <div wire:ignore.self class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">

        <div class="modal-header">
          <h5 class="modal-title">{{ $isEdit ? 'Edit User' : 'Create User' }}</h5>
        </div>

        <form wire:submit.prevent="{{ $isEdit ? 'update' : 'store' }}">
          <div class="modal-body">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nama</label>
                <input type="text" class="form-control" wire:model.defer="name" placeholder="Nama user">
                @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" wire:model.defer="email" placeholder="email@domain.com">
                @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Role</label>
                <select class="form-select" wire:model.defer="role">
                  @foreach ($roleOptions as $r)
                  <option value="{{ $r }}">{{ $r }}</option>
                  @endforeach
                </select>
                @error('role') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Profile Image</label>
                <input type="file" class="form-control" wire:model="profile_image" accept="image/*">
                <div class="form-text">
                  @if (!empty($existing_profile_image))
                  Current: <span class="text-muted">{{ $existing_profile_image }}</span>
                  @endif
                </div>
                @error('profile_image') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
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
                  placeholder="{{ $isEdit ? 'Ketik Password' : 'Minimal 8 karakter' }}">
                @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" class="form-control" wire:model.defer="password_confirmation"
                  placeholder="Ulangi password">
              </div>

              <div class="col-12">
                <label class="form-label">Sites (boleh banyak)</label>

                <select class="form-select" multiple size="6" wire:model.defer="sites">
                  @foreach ($siteOptions as $opt)
                  <option value="{{ $opt }}">{{ strtoupper($opt) }}</option>
                  @endforeach
                </select>

                @error('sites') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @error('sites.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                <div class="form-text">
                  Tahan <b>Ctrl</b> (Windows) / <b>Cmd</b> (Mac) untuk pilih lebih dari satu.
                </div>
              </div>
            </div>

          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" wire:click="closeModal">
              Batal
            </button>

            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"
              wire:target="{{ $isEdit ? 'update' : 'store' }},profile_image">
              <span wire:loading.remove wire:target="{{ $isEdit ? 'update' : 'store' }},profile_image">
                {{ $isEdit ? 'Update' : 'Create' }}
              </span>
              <span wire:loading wire:target="{{ $isEdit ? 'update' : 'store' }},profile_image">
                Processing...
              </span>
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>

{{-- Bootstrap modal bridge for Livewire --}}
<script>
  document.addEventListener('livewire:init', () => {
    const el = document.getElementById('userModal');
    const modal = el ? new bootstrap.Modal(el) : null;

    Livewire.on('user-modal:open', () => modal?.show());
    Livewire.on('user-modal:close', () => modal?.hide());
  });
</script>
