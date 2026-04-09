<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vlan extends Model
{
    protected $appends = ['netmask'];

    public function getNetmaskAttribute(): ?string
    {
        $network = (string) ($this->network ?? '');
        $network = trim($network);

        if ($network === '' || !str_contains($network, '/')) {
            return null;
        }

        [, $cidr] = explode('/', $network, 2);
        $cidr = (int) trim($cidr);

        if ($cidr < 0 || $cidr > 32) {
            return null;
        }

        // bikin netmask aman (hindari shift error)
        $mask = $cidr === 0 ? 0 : ((0xFFFFFFFF << (32 - $cidr)) & 0xFFFFFFFF);

        return long2ip($mask);
    }
}