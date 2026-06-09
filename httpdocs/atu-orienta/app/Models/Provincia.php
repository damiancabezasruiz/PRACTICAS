<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provincia extends Model
{
    protected $table = 'provincias';

    protected $fillable = [
        'nombre',
        'comunidad_id',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function localidades()
    {
        return $this->hasMany(Localidad::class);
    }
}