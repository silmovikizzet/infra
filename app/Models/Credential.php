<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Credential extends Model
{
    protected $fillable = [
        'name',
        'username',
        'password',
        'port',
        'type',
    ];

    // jika ingin auto-encrypt saat set
    public function setPasswordAttribute($value)
    {
        if (is_null($value) || $value === '') {
            $this->attributes['password'] = $value;
            return;
        }

        // Jika sudah tampak terenkripsi (opsional) — kita bisa coba decrypt untuk deteksi
        try {
            // kalau bisa di-decrypt berarti sudah terenkripsi, simpan apa adanya
            Crypt::decryptString($value);
            $this->attributes['password'] = $value;
            return;
        } catch (\Throwable $e) {
            // bukan string terenkripsi: encrypt sekarang
        }

        $this->attributes['password'] = Crypt::encryptString($value);
    }

    // auto-decrypt saat diakses
    public function getPasswordAttribute($value)
    {
        if (is_null($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // kalau gagal decrypt (MAC invalid / bukan terenkripsi),
            // kembalikan nilai mentah agar tidak memutus alur; log untuk debugging
            Log::warning('Credential::getPasswordAttribute - decrypt failed', [
                'id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return $value;
        }
    }

    // Relasi ke Asset (optional)
    public function assets()
    {
        return $this->hasMany(Asset::class, 'credential_id');
    }
}
