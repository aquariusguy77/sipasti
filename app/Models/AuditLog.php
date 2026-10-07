<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Hanya-tambah. Pemicu basis data menolak perintah ubah dan hapus.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
}
