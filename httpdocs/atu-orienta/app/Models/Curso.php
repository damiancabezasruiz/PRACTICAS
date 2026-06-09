<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $fillable = [
        'titulo',
        'descripcion',
        'area',
        'modalidad',
        'comunidad',
        'provincia',
        'ciudad',
        'nivel',
        'situacion_permitida',
        'requisitos',
        'enlace',
        'activo',
    ];
}
