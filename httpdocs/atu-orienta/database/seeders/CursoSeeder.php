<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Curso;

class CursoSeeder extends Seeder
{
    public function run(): void
    {
        Curso::create([
            'titulo' => 'Desarrollo de aplicaciones web con PHP',
            'descripcion' => 'Curso orientado al desarrollo web con PHP, Laravel, MySQL y buenas prácticas.',
            'area' => 'informatica',
            'modalidad' => 'online',
            'provincia' => 'Valladolid',
            'ciudad' => 'Valladolid',
            'nivel' => 'medio',
            'situacion_permitida' => 'desempleado,trabajador',
            'requisitos' => 'Conocimientos básicos de programación.',
            'enlace' => 'https://cursosatu.grupoatu.com',
            'activo' => true,
        ]);

        Curso::create([
            'titulo' => 'Administración de bases de datos MySQL',
            'descripcion' => 'Aprende a diseñar, administrar y consultar bases de datos relacionales.',
            'area' => 'informatica',
            'modalidad' => 'presencial',
            'provincia' => 'Burgos',
            'ciudad' => 'Burgos',
            'nivel' => 'medio',
            'situacion_permitida' => 'desempleado,trabajador,autonomo',
            'requisitos' => 'Conocimientos básicos de informática.',
            'enlace' => 'https://cursosatu.grupoatu.com',
            'activo' => true,
        ]);

        Curso::create([
            'titulo' => 'Gestión logística y almacén',
            'descripcion' => 'Curso para mejorar competencias en logística, almacén y distribución.',
            'area' => 'logistica',
            'modalidad' => 'mixta',
            'provincia' => 'León',
            'ciudad' => 'León',
            'nivel' => 'basico',
            'situacion_permitida' => 'desempleado,trabajador,erte',
            'requisitos' => 'No se requieren conocimientos previos.',
            'enlace' => 'https://cursosatu.grupoatu.com',
            'activo' => true,
        ]);

        Curso::create([
            'titulo' => 'Ofimática avanzada',
            'descripcion' => 'Curso de herramientas ofimáticas para mejorar la empleabilidad.',
            'area' => 'administracion',
            'modalidad' => 'online',
            'provincia' => 'Palencia',
            'ciudad' => 'Palencia',
            'nivel' => 'basico',
            'situacion_permitida' => 'desempleado,trabajador,autonomo,erte',
            'requisitos' => 'Manejo básico del ordenador.',
            'enlace' => 'https://cursosatu.grupoatu.com',
            'activo' => true,
        ]);

        Curso::create([
            'titulo' => 'Ciberseguridad básica',
            'descripcion' => 'Introducción a la seguridad informática, protección de datos y buenas prácticas digitales.',
            'area' => 'informatica',
            'modalidad' => 'online',
            'provincia' => 'Salamanca',
            'ciudad' => 'Salamanca',
            'nivel' => 'basico',
            'situacion_permitida' => 'desempleado,trabajador',
            'requisitos' => 'Interés por la tecnología.',
            'enlace' => 'https://cursosatu.grupoatu.com',
            'activo' => true,
        ]);
    }
}