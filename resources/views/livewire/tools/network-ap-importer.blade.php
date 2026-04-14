<div class="kt-card">
  <div class="kt-card-header">
    <h3 class="kt-card-title">Import Asset AP (CSV / Excel)</h3>
  </div>

  <div class="kt-card-body p-6! space-y-5">
    <div class="grid gap-3">
      <div>
        <label class="kt-form-label">File</label>
        <input type="file" class="kt-input" wire:model="file" accept=".csv,.xlsx,.xls" />

        @error('file')
        <div class="text-sm text-destructive mt-1">{{ $message }}</div>
        @enderror

        <div class="text-xs text-secondary-foreground mt-2">
          Format didukung: CSV, XLSX, XLS. Maks 20MB.
        </div>
      </div>
      <label class="kt-form-label">Delimiter CSV</label>
      <select class="kt-input" wire:model.live="csvDelimiter">
        <option value="auto">Auto detect</option>
        <option value=",">Comma (,)</option>
        <option value=";">Semicolon (;)</option>
        <option value="tab">Tab</option>
        <option value="|">Pipe (|)</option>
      </select>

      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" class="kt-checkbox" wire:model.live="hasHeader">
        <span>File memiliki header (baris pertama adalah nama kolom)</span>
      </label>

      <div class="flex flex-wrap items-center gap-2">
        <button class="kt-btn kt-btn-primary" wire:click="import" wire:loading.attr="disabled"
          wire:target="import,file">
          <span wire:loading.remove wire:target="import">Import</span>
          <span wire:loading wire:target="import">Memproses...</span>
        </button>

        <div class="text-sm text-secondary-foreground">
          Created: <b>{{ $created }}</b> |
          Updated: <b>{{ $updated }}</b> |
          Skipped: <b>{{ $skipped }}</b> |
          Failed: <b>{{ $failed }}</b>
        </div>
      </div>

      <div class="kt-separator"></div>

      <div class="text-sm">
        <div class="font-semibold mb-2">Header yang disarankan</div>
        <div class="text-secondary-foreground">
          hostname, category, type, group, ip_address, mac_address, serial_number, end_of_support,
          warranty, firmware_version, location, floor, tower, credential_id, remark
        </div>
        <div class="text-xs text-secondary-foreground mt-2">
          Variasi seperti "IP Address" / "Serial Number" juga otomatis kebaca.
        </div>
      </div>

      @if (!empty($errorsList))
      <div class="kt-alert kt-alert-danger">
        <div class="font-semibold mb-2">Beberapa baris gagal diproses (menampilkan maks 50 error terakhir)</div>
        <div class="space-y-1 text-sm">
          @foreach ($errorsList as $e)
          <div>Row {{ $e['row'] }}: {{ $e['message'] }}</div>
          @endforeach
        </div>
      </div>
      @endif
    </div>
  </div>
</div>
