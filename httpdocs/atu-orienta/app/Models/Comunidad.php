<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Comunidad extends Model
{
    protected $table = 'comunidades';

    protected $fillable = [
        'nombre',
    ];

    public function provincias()
    {
        return $this->hasMany(Provincia::class);
    }
}