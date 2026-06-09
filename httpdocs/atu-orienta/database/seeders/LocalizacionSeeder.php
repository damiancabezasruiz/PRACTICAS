<?php

namespace Database\Seeders;

use App\Models\Comunidad;
use App\Models\Provincia;
use App\Models\Localidad;
use Illuminate\Database\Seeder;

class LocalizacionSeeder extends Seeder
{
    public function run(): void
    {
        $datos = [
            'Andalucía' => [
                'Almería' => ['Almería', 'El Ejido', 'Roquetas de Mar'],
                'Cádiz' => ['Cádiz', 'Jerez de la Frontera', 'Algeciras'],
                'Córdoba' => ['Córdoba', 'Lucena', 'Puente Genil'],
                'Granada' => ['Granada', 'Motril', 'Armilla'],
                'Huelva' => ['Huelva', 'Lepe', 'Almonte'],
                'Jaén' => ['Jaén', 'Linares', 'Úbeda'],
                'Málaga' => ['Málaga', 'Marbella', 'Vélez-Málaga'],
                'Sevilla' => ['Sevilla', 'Dos Hermanas', 'Alcalá de Guadaíra'],
            ],
            'Aragón' => [
                'Huesca' => ['Huesca', 'Barbastro', 'Monzón'],
                'Teruel' => ['Teruel', 'Alcañiz', 'Andorra'],
                'Zaragoza' => ['Zaragoza', 'Calatayud', 'Utebo'],
            ],
            'Asturias' => [
                'Asturias' => ['Oviedo', 'Gijón', 'Avilés'],
            ],
            'Illes Balears' => [
                'Illes Balears' => ['Palma', 'Ibiza', 'Manacor'],
            ],
            'Canarias' => [
                'Las Palmas' => ['Las Palmas de Gran Canaria', 'Telde', 'Arrecife'],
                'Santa Cruz de Tenerife' => ['Santa Cruz de Tenerife', 'La Laguna', 'Arona'],
            ],
            'Cantabria' => [
                'Cantabria' => ['Santander', 'Torrelavega', 'Camargo'],
            ],
            'Castilla-La Mancha' => [
                'Albacete' => ['Albacete', 'Hellín', 'Villarrobledo'],
                'Ciudad Real' => ['Ciudad Real', 'Puertollano', 'Tomelloso'],
                'Cuenca' => ['Cuenca', 'Tarancón', 'San Clemente'],
                'Guadalajara' => ['Guadalajara', 'Azuqueca de Henares', 'Sigüenza'],
                'Toledo' => ['Toledo', 'Talavera de la Reina', 'Illescas'],
            ],
            'Castilla y León' => [
                'Ávila' => ['Ávila', 'Arévalo', 'Arenas de San Pedro'],
                'Burgos' => ['Burgos', 'Aranda de Duero', 'Miranda de Ebro'],
                'León' => ['León', 'Ponferrada', 'San Andrés del Rabanedo'],
                'Palencia' => ['Palencia', 'Aguilar de Campoo', 'Venta de Baños'],
                'Salamanca' => ['Salamanca', 'Béjar', 'Ciudad Rodrigo'],
                'Segovia' => ['Segovia', 'Cuéllar', 'El Espinar'],
                'Soria' => ['Soria', 'Almazán', 'El Burgo de Osma'],
                'Valladolid' => ['Valladolid', 'Laguna de Duero', 'Medina del Campo'],
                'Zamora' => ['Zamora', 'Benavente', 'Toro'],
            ],
            'Cataluña' => [
                'Barcelona' => ['Barcelona', 'Hospitalet de Llobregat', 'Badalona'],
                'Girona' => ['Girona', 'Figueres', 'Blanes'],
                'Lleida' => ['Lleida', 'Balaguer', 'Tàrrega'],
                'Tarragona' => ['Tarragona', 'Reus', 'Tortosa'],
            ],
            'Comunitat Valenciana' => [
                'Alicante' => ['Alicante', 'Elche', 'Benidorm'],
                'Castellón' => ['Castellón de la Plana', 'Vila-real', 'Burriana'],
                'Valencia' => ['Valencia', 'Torrent', 'Gandía'],
            ],
            'Extremadura' => [
                'Badajoz' => ['Badajoz', 'Mérida', 'Don Benito'],
                'Cáceres' => ['Cáceres', 'Plasencia', 'Trujillo'],
            ],
            'Galicia' => [
                'A Coruña' => ['A Coruña', 'Santiago de Compostela', 'Ferrol'],
                'Lugo' => ['Lugo', 'Monforte de Lemos', 'Vilalba'],
                'Ourense' => ['Ourense', 'Verín', 'O Barco de Valdeorras'],
                'Pontevedra' => ['Pontevedra', 'Vigo', 'Vilagarcía de Arousa'],
            ],
            'Madrid' => [
                'Madrid' => ['Madrid', 'Alcalá de Henares', 'Getafe'],
            ],
            'Murcia' => [
                'Murcia' => ['Murcia', 'Cartagena', 'Lorca'],
            ],
            'Navarra' => [
                'Navarra' => ['Pamplona', 'Tudela', 'Estella'],
            ],
            'País Vasco' => [
                'Álava' => ['Vitoria-Gasteiz', 'Llodio', 'Amurrio'],
                'Bizkaia' => ['Bilbao', 'Barakaldo', 'Getxo'],
                'Gipuzkoa' => ['San Sebastián', 'Irun', 'Eibar'],
            ],
            'La Rioja' => [
                'La Rioja' => ['Logroño', 'Calahorra', 'Arnedo'],
            ],
        ];

        foreach ($datos as $nombreComunidad => $provincias) {
            $comunidad = Comunidad::create([
                'nombre' => $nombreComunidad,
            ]);

            foreach ($provincias as $nombreProvincia => $localidades) {
                $provincia = Provincia::create([
                    'comunidad_id' => $comunidad->id,
                    'nombre' => $nombreProvincia,
                ]);

                foreach ($localidades as $nombreLocalidad) {
                    Localidad::create([
                        'provincia_id' => $provincia->id,
                        'nombre' => $nombreLocalidad,
                    ]);
                }
            }
        }
    }
}