<?php

declare(strict_types=1);

namespace App\Livewire\Tools;
use Livewire\Attributes\Title;
use App\Models\AssetSwitch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

use PhpOffice\PhpSpreadsheet\IOFactory;

#[Layout('layouts.contentNavbarLayoutLivewire')]
#[Title('Firewall')]
class NetworkSwitchImporter extends Component
{
  use WithFileUploads;

  public $file; // UploadedFile

  public bool $hasHeader = true;
  public string $csvDelimiter = 'auto';
  // hasil
  public int $created = 0;
  public int $updated = 0;
  public int $skipped = 0;
  public int $failed = 0;

  public array $errorsList = []; // kumpulan error per row (ringkas)

  public function rules(): array
  {
    return [
      'file' => ['required', 'file', 'max:20480'], // 20MB
    ];
  }

  public function render()
  {
    return view('livewire.tools.network-switch-importer');
  }

  public function import(): void
  {
    $this->resetResult();

    $this->validate();

    $ext = strtolower($this->file->getClientOriginalExtension() ?: '');
    $path = $this->file->getRealPath();

    if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
      $this->addError('file', 'Format file harus CSV / XLSX / XLS.');
      return;
    }

    try {
      $rows = $ext === 'csv'
        ? $this->readCsvRows($path)
        : $this->readExcelRows($path);

      if (count($rows) === 0) {
        $this->addError('file', 'File kosong / tidak ada data.');
        return;
      }

      // Ambil header & buat map kolom
      $header = [];
      if ($this->hasHeader) {
        $header = array_shift($rows);
        $map = $this->buildHeaderMap($header);
      } else {
        // Jika tanpa header, anggap urutan kolom sesuai fillable
        $map = $this->defaultIndexMap();
      }

      DB::beginTransaction();

      $rowNumber = $this->hasHeader ? 2 : 1;

      foreach ($rows as $r) {
        try {
          $data = $this->rowToData($r, $map);

          // minimal data biar masuk akal
          $hostname = trim((string) ($data['hostname'] ?? ''));
          $ip = trim((string) ($data['ip_address'] ?? ''));
          $serial = trim((string) ($data['serial_number'] ?? ''));

          if ($hostname === '' && $ip === '' && $serial === '') {
            $this->skipped++;
            $rowNumber++;
            continue;
          }

          // normalisasi
          $data['mac_address'] = $this->normalizeMac((string) ($data['mac_address'] ?? ''));

          // bersihkan string kosong => null untuk field tertentu
          $data = $this->emptyToNull($data, ['credential_id']);

          // update-or-create key
          $lookup = [];
          if ($serial !== '') {
            $lookup = ['serial_number' => $serial];
          } else {
            $lookup = [
              'hostname' => $hostname,
              'ip_address' => $ip,
            ];
          }

          // Kalau masih kosong juga, skip
          if (($lookup['serial_number'] ?? '') === '' && ($lookup['hostname'] ?? '') === '' && ($lookup['ip_address'] ?? '') === '') {
            $this->skipped++;
            $rowNumber++;
            continue;
          }

          $existing = AssetSwitch::query()->where($lookup)->first();

          if ($existing) {
            $existing->fill($data);
            $dirty = $existing->isDirty();
            if ($dirty) {
              $existing->save();
              $this->updated++;
            } else {
              $this->skipped++;
            }
          } else {
            AssetSwitch::create($data);
            $this->created++;
          }
        } catch (\Throwable $e) {
          $this->failed++;
          $this->pushRowError($rowNumber, $e->getMessage());
          Log::warning('[AssetSwitch Import] Row failed', [
            'row' => $rowNumber,
            'error' => $e->getMessage(),
          ]);
        }

        $rowNumber++;
      }

      DB::commit();

      $this->dispatch('toast', message: "Import selesai. Created {$this->created}, Updated {$this->updated}, Skipped {$this->skipped}, Failed {$this->failed}.", variant: 'success');
    } catch (\Throwable $e) {
      DB::rollBack();
      Log::error('[AssetSwitch Import] Fatal', ['error' => $e->getMessage()]);
      $this->addError('file', 'Gagal import: ' . $e->getMessage());
    }
  }

  private function resetResult(): void
  {
    $this->created = 0;
    $this->updated = 0;
    $this->skipped = 0;
    $this->failed = 0;
    $this->errorsList = [];
    $this->resetErrorBag();
  }


  /**
   * Excel reader via PhpSpreadsheet.
   */
  private function readExcelRows(string $path): array
  {
    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();

    // toArray: [row][col]
    $arr = $sheet->toArray(null, true, true, false);

    // filter row kosong
    $rows = [];
    foreach ($arr as $row) {
      if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
        continue;
      }
      $rows[] = $row;
    }

    return $rows;
  }

  /**
   * Buat map: index kolom -> field db.
   * Mendukung variasi header (contoh: "IP Address", "ip", "ip_address", dll).
   */
  private function buildHeaderMap(array $headerRow): array
  {
    $map = [];

    $normalize = function ($h): string {
      $h = strtolower(trim((string) $h));
      $h = str_replace(['-', '.', '/', '\\'], ' ', $h);
      $h = preg_replace('/\s+/', ' ', $h);
      return $h;
    };

    $aliases = [
      'hostname' => ['hostname', 'host', 'device', 'device name', 'switch', 'switch name'],
      'category' => ['category', 'kategori'],
      'type' => ['type', 'tipe', 'model'],
      'group' => ['group', 'grup'],
      'ip_address' => ['ip address', 'ip', 'ipaddr', 'ip_address', 'address'],
      'mac_address' => ['mac address', 'mac', 'mac_address'],
      'serial_number' => ['serial number', 'serial', 'sn', 'serial_number'],
      'end_of_support' => ['end of support', 'eos', 'end_of_support'],
      'warranty' => ['warranty', 'garansi'],
      'firmware_version' => ['firmware version', 'firmware', 'os version', 'firmware_version'],
      'location' => ['location', 'lokasi', 'site'],
      'floor' => ['floor', 'lantai'],
      'tower' => ['tower', 'gedung', 'building'],
      'credential_id' => ['credential id', 'credential', 'credential_id'],
      'remark' => ['remark', 'remarks', 'note', 'notes', 'keterangan'],
    ];

    foreach ($headerRow as $idx => $rawHeader) {
      $h = $normalize($rawHeader);

      foreach ($aliases as $field => $list) {
        foreach ($list as $a) {
          if ($h === $normalize($a)) {
            $map[(int) $idx] = $field;
            continue 3;
          }
        }
      }

      // fallback: coba jadi snake_case, kalau match fillable -> map
      $snake = Str::snake($h);
      if (in_array($snake, (new AssetSwitch())->getFillable(), true)) {
        $map[(int) $idx] = $snake;
      }
    }

    return $map;
  }

  /**
   * Jika tanpa header: urutan kolom = urutan fillable
   */
  private function defaultIndexMap(): array
  {
    $fillable = (new AssetSwitch())->getFillable();

    $map = [];
    foreach ($fillable as $i => $field) {
      $map[$i] = $field;
    }
    return $map;
  }

  private function rowToData(array $row, array $map): array
  {
    $data = [];

    foreach ($map as $idx => $field) {
      $val = $row[$idx] ?? null;
      if (is_string($val)) {
        $val = trim($val);
      }
      $data[$field] = $val;
    }

    // pastikan hanya fillable yang masuk
    $fillable = array_flip((new AssetSwitch())->getFillable());
    $data = array_intersect_key($data, $fillable);

    return $data;
  }

  private function normalizeMac(string $mac): ?string
  {
    $mac = strtolower(trim($mac));
    if ($mac === '' || $mac === 'n/a' || $mac === '-') {
      return null;
    }

    // buang semua selain hex
    $hex = preg_replace('/[^0-9a-f]/i', '', $mac);
    if (!$hex || strlen($hex) !== 12) {
      // biarin apa adanya kalau format aneh, tapi tetap simpan original (opsional)
      return $mac;
    }

    // jadi aa:bb:cc:dd:ee:ff
    return implode(':', str_split($hex, 2));
  }

  private function emptyToNull(array $data, array $fields): array
  {
    foreach ($fields as $f) {
      if (array_key_exists($f, $data)) {
        $v = $data[$f];
        if (is_string($v) && trim($v) === '') {
          $data[$f] = null;
        }
      }
    }
    return $data;
  }

  private function pushRowError(int $rowNumber, string $message): void
  {
    $this->errorsList[] = [
      'row' => $rowNumber,
      'message' => Str::limit($message, 200),
    ];

    // jaga biar gak kepanjangan
    if (count($this->errorsList) > 50) {
      array_shift($this->errorsList);
    }
  }
}
