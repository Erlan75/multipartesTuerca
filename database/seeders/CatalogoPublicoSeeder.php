<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vehiculo;
use App\Models\Compatibilidad;

class CatalogoPublicoSeeder extends Seeder
{
    public function run(): void
    {
        $vehiculosData = [
            // Mazda
            ['marca' => 'Mazda', 'modelo' => 'CX-5', 'anio_inicio' => 2013, 'anio_fin' => 2018, 'motor_cilindrada' => '2.5L SkyActiv-G', 'combustible' => 'Gasolina'],
            ['marca' => 'Mazda', 'modelo' => 'BT-50', 'anio_inicio' => 2012, 'anio_fin' => 2020, 'motor_cilindrada' => '3.2L MZ-CD', 'combustible' => 'Diésel'],
            // Mitsubishi
            ['marca' => 'Mitsubishi', 'modelo' => 'L200 Triton', 'anio_inicio' => 2007, 'anio_fin' => 2016, 'motor_cilindrada' => '2.5L Di-D', 'combustible' => 'Diésel'],
            ['marca' => 'Mitsubishi', 'modelo' => 'Montero Sport', 'anio_inicio' => 2008, 'anio_fin' => 2016, 'motor_cilindrada' => '3.0L V6', 'combustible' => 'Gasolina'],
            ['marca' => 'Mitsubishi', 'modelo' => 'Outlander', 'anio_inicio' => 2013, 'anio_fin' => 2019, 'motor_cilindrada' => '2.4L MIVEC', 'combustible' => 'Gasolina'],
            // Honda
            ['marca' => 'Honda', 'modelo' => 'Civic', 'anio_inicio' => 2006, 'anio_fin' => 2012, 'motor_cilindrada' => '1.8L i-VTEC', 'combustible' => 'Gasolina'],
            ['marca' => 'Honda', 'modelo' => 'CR-V', 'anio_inicio' => 2007, 'anio_fin' => 2012, 'motor_cilindrada' => '2.4L i-VTEC', 'combustible' => 'Gasolina'],
            ['marca' => 'Honda', 'modelo' => 'HR-V', 'anio_inicio' => 2016, 'anio_fin' => 2021, 'motor_cilindrada' => '1.8L i-VTEC', 'combustible' => 'Gasolina'],
            // Hyundai
            ['marca' => 'Hyundai', 'modelo' => 'Tucson', 'anio_inicio' => 2010, 'anio_fin' => 2015, 'motor_cilindrada' => '2.0L DOHC', 'combustible' => 'Gasolina'],
            ['marca' => 'Hyundai', 'modelo' => 'Santa Fe', 'anio_inicio' => 2007, 'anio_fin' => 2013, 'motor_cilindrada' => '2.4L Theta II', 'combustible' => 'Gasolina'],
            ['marca' => 'Hyundai', 'modelo' => 'Creta', 'anio_inicio' => 2016, 'anio_fin' => 2021, 'motor_cilindrada' => '1.6L Gamma', 'combustible' => 'Gasolina'],
            // Kia
            ['marca' => 'Kia', 'modelo' => 'Sportage', 'anio_inicio' => 2010, 'anio_fin' => 2016, 'motor_cilindrada' => '2.0L CVVT', 'combustible' => 'Gasolina'],
            ['marca' => 'Kia', 'modelo' => 'Rio', 'anio_inicio' => 2012, 'anio_fin' => 2017, 'motor_cilindrada' => '1.4L MPI', 'combustible' => 'Gasolina'],
            ['marca' => 'Kia', 'modelo' => 'Sorento', 'anio_inicio' => 2009, 'anio_fin' => 2015, 'motor_cilindrada' => '2.4L DOHC', 'combustible' => 'Gasolina'],
            // Isuzu
            ['marca' => 'Isuzu', 'modelo' => 'D-Max', 'anio_inicio' => 2008, 'anio_fin' => 2015, 'motor_cilindrada' => '3.0L Ddi i-TEQ', 'combustible' => 'Diésel'],
            ['marca' => 'Isuzu', 'modelo' => 'D-Max', 'anio_inicio' => 2016, 'anio_fin' => 2022, 'motor_cilindrada' => '2.5L Turbo', 'combustible' => 'Diésel'],
            ['marca' => 'Isuzu', 'modelo' => 'MU-X', 'anio_inicio' => 2014, 'anio_fin' => 2020, 'motor_cilindrada' => '3.0L Ddi', 'combustible' => 'Diésel'],
        ];

        foreach ($vehiculosData as $v) {
            $exists = Vehiculo::where('marca', $v['marca'])->where('modelo', $v['modelo'])->where('anio_inicio', $v['anio_inicio'])->first();
            if (!$exists) {
                Vehiculo::create($v);
            }
        }

        // Add some realistic compatibilities for these new vehicles
        $mazda3 = Vehiculo::where('marca', 'Mazda')->where('modelo', 'Mazda 3')->first();
        $mazdaCx5 = Vehiculo::where('marca', 'Mazda')->where('modelo', 'CX-5')->first();
        $mitsubishiL200 = Vehiculo::where('marca', 'Mitsubishi')->where('modelo', 'L200 Triton')->first();
        $hondaCivic = Vehiculo::where('marca', 'Honda')->where('modelo', 'Civic')->first();
        $hondaCrv = Vehiculo::where('marca', 'Honda')->where('modelo', 'CR-V')->first();
        $hyundaiTucson = Vehiculo::where('marca', 'Hyundai')->where('modelo', 'Tucson')->first();
        $kiaSportage = Vehiculo::where('marca', 'Kia')->where('modelo', 'Sportage')->first();
        $isuzuDmax = Vehiculo::where('marca', 'Isuzu')->where('modelo', 'D-Max')->first();

        $compatibilidades = [
            // Bujías (15, 16), Filtros (17, 18, 19, 20), Pastillas (1, 3, 5), Luces (28), Relé (27), Bomba (22, 29), Termostato (30)
            ['id_producto' => 16, 'vehiculo' => $mazda3, 'pos' => 'Motor 2.0L SkyActiv - Luz de electrodos 1.1mm'],
            ['id_producto' => 18, 'vehiculo' => $mazda3, 'pos' => 'Filtro aceite rosca M20x1.5'],
            ['id_producto' => 28, 'vehiculo' => $mazda3, 'pos' => 'Faros principales H4'],
            ['id_producto' => 1,  'vehiculo' => $mazdaCx5, 'pos' => 'Eje Delantero'],
            ['id_producto' => 19, 'vehiculo' => $mazdaCx5, 'pos' => 'Caja filtro admisión'],

            ['id_producto' => 18, 'vehiculo' => $mitsubishiL200, 'pos' => 'Filtro aceite motor Di-D'],
            ['id_producto' => 20, 'vehiculo' => $mitsubishiL200, 'pos' => 'Filtro combustible línea de alta presión'],
            ['id_producto' => 27, 'vehiculo' => $mitsubishiL200, 'pos' => 'Caja de fusibles bahía de motor'],
            ['id_producto' => 4,  'vehiculo' => $mitsubishiL200, 'pos' => 'Eje Trasero - Zapatas de tambor'],

            ['id_producto' => 15, 'vehiculo' => $hondaCivic, 'pos' => 'Bujías Iridium motor 1.8L i-VTEC'],
            ['id_producto' => 17, 'vehiculo' => $hondaCivic, 'pos' => 'Filtro de aceite blindado'],
            ['id_producto' => 26, 'vehiculo' => $hondaCivic, 'pos' => 'Bobina encendido cil 1-4'],
            ['id_producto' => 1,  'vehiculo' => $hondaCrv, 'pos' => 'Pastillas de freno eje delantero'],
            ['id_producto' => 30, 'vehiculo' => $hondaCrv, 'pos' => 'Circuito refrigeración termostato 82C'],

            ['id_producto' => 15, 'vehiculo' => $hyundaiTucson, 'pos' => 'Motor 2.0 DOHC Theta'],
            ['id_producto' => 17, 'vehiculo' => $hyundaiTucson, 'pos' => 'Filtro aceite motor'],
            ['id_producto' => 22, 'vehiculo' => $hyundaiTucson, 'pos' => 'Tanque de combustible - Bomba 3 bar'],
            ['id_producto' => 12, 'vehiculo' => $hyundaiTucson, 'pos' => 'Eje delantero suspensión'],

            ['id_producto' => 15, 'vehiculo' => $kiaSportage, 'pos' => 'Motor 2.0 CVVT'],
            ['id_producto' => 18, 'vehiculo' => $kiaSportage, 'pos' => 'Filtro aceite motor'],
            ['id_producto' => 27, 'vehiculo' => $kiaSportage, 'pos' => 'Relé compresor / luces 12V'],
            ['id_producto' => 3,  'vehiculo' => $kiaSportage, 'pos' => 'Pastillas delanteras cerámicas'],

            ['id_producto' => 20, 'vehiculo' => $isuzuDmax, 'pos' => 'Filtro combustible motor 3.0 Ddi'],
            ['id_producto' => 18, 'vehiculo' => $isuzuDmax, 'pos' => 'Filtro aceite blindado'],
            ['id_producto' => 29, 'vehiculo' => $isuzuDmax, 'pos' => 'Bomba de agua circuito refrigeración'],
        ];

        foreach ($compatibilidades as $item) {
            if ($item['vehiculo']) {
                $compExists = Compatibilidad::where('id_producto', $item['id_producto'])
                    ->where('id_vehiculo', $item['vehiculo']->id_vehiculo)
                    ->first();
                if (!$compExists) {
                    Compatibilidad::create([
                        'id_producto' => $item['id_producto'],
                        'id_vehiculo' => $item['vehiculo']->id_vehiculo,
                        'notas_posicion' => $item['pos'],
                    ]);
                }
            }
        }
    }
}
