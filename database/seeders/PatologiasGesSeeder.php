<?php

namespace Database\Seeders;

use App\Models\Patologia;
use Illuminate\Database\Seeder;

class PatologiasGesSeeder extends Seeder
{
    public function run(): void
    {
        $patologias = [
            1 => 'Enfermedad renal crónica etapa 4 y 5',
            2 => 'Cardiopatías congénitas operables en menores de 15 años',
            3 => 'Cáncer cérvico-uterino',
            4 => 'Alivio del dolor y cuidados paliativos por cáncer avanzado',
            5 => 'Infarto agudo de miocardio',
            6 => 'Diabetes Mellitus tipo I',
            7 => 'Diabetes Mellitus tipo II',
            8 => 'Cáncer de mama en personas de 15 años y más',
            9 => 'Disrafias espinales',
            10 => 'Tratamiento quirúrgico de escoliosis en personas menores de 25 años',
            11 => 'Tratamiento quirúrgico de cataratas',
            12 => 'Endoprótesis total de cadera en personas de 65 años y más con artrosis de cadera con limitación funcional severa',
            13 => 'Fisura labiopalatina',
            14 => 'Cáncer en personas menores de 15 años',
            15 => 'Esquizofrenia',
            16 => 'Cáncer de testículo en personas de 15 años y más',
            17 => 'Linfomas en personas de 15 años y más',
            18 => 'Síndrome de la inmunodeficiencia adquirida VIH/SIDA',
            19 => 'Infección respiratoria aguda (IRA) de manejo ambulatorio en personas menores de 5 años',
            20 => 'Neumonía adquirida en la comunidad de manejo ambulatorio en personas de 65 años y más',
            21 => 'Hipertensión arterial primaria o esencial en personas de 15 años y más',
            22 => 'Epilepsia no refractaria en personas desde 1 año y menores de 15 años',
            23 => 'Salud oral integral para niños y niñas de 6 años',
            24 => 'Prevención de parto prematuro',
            25 => 'Trastornos de generación del impulso y conducción en personas de 15 años y más que requieren marcapaso',
            26 => 'Colecistectomía preventiva del cáncer de vesícula en personas de 35 a 49 años',
            27 => 'Cáncer gástrico',
            28 => 'Cáncer de próstata en personas de 15 años y más',
            29 => 'Vicios de refracción en personas de 65 años y más',
            30 => 'Estrabismo en personas menores de 9 años',
            31 => 'Retinopatía diabética',
            32 => 'Desprendimiento de retina regmatógeno no traumático',
            33 => 'Hemofilia',
            34 => 'Depresión en personas de 15 años y más',
            35 => 'Tratamiento de la hiperplasia benigna de la próstata en personas sintomáticas',
            36 => 'Ayudas Técnicas para personas de 65 años y más',
            37 => 'Ataque cerebrovascular isquémico en personas de 15 años y más',
            38 => 'Enfermedad pulmonar obstructiva crónica de tratamiento ambulatorio',
            39 => 'Asma bronquial moderada y grave en menores de 15 años',
            40 => 'Síndrome de dificultad respiratoria en el recién nacido',
            41 => 'Tratamiento médico en personas de 55 años y más con artrosis de cadera y/o rodilla, leve o moderada',
            42 => 'Hemorragia subaracnoidea secundaria a ruptura de aneurismas cerebrales',
            43 => 'Tumores primarios del sistema nervioso central en personas de 15 años y más',
            44 => 'Tratamiento quirúrgico de hernia del núcleo pulposo lumbar',
            45 => 'Leucemia en personas de 15 años y más',
            46 => 'Urgencia odontológica ambulatoria',
            47 => 'Salud oral integral del adulto de 60 años',
            48 => 'Politraumatizado grave',
            49 => 'Traumatismo cráneo encefálico moderado o grave',
            50 => 'Trauma ocular grave',
            51 => 'Fibrosis quística',
            52 => 'Artritis reumatoidea',
            53 => 'Consumo perjudicial o dependencia de riesgo bajo a moderado de alcohol y drogas en personas menores de 20 años',
            54 => 'Analgesia del parto',
            55 => 'Gran quemado',
            56 => 'Hipoacusia bilateral en personas de 65 años y más que requieren uso de audífono',
            57 => 'Retinopatía del prematuro',
            58 => 'Displasia broncopulmonar del prematuro',
            59 => 'Hipoacusia neurosensorial bilateral del prematuro',
            60 => 'Epilepsia no refractaria en personas de 15 años y más',
            61 => 'Asma bronquial en personas de 15 años y más',
            62 => 'Enfermedad de Parkinson',
            63 => 'Artritis idiopática juvenil',
            64 => 'Prevención secundaria enfermedad renal crónica terminal',
            65 => 'Displasia luxante de caderas',
            66 => 'Salud oral integral de la embarazada',
            67 => 'Esclerosis múltiple remitente recurrente',
            68 => 'Hepatitis crónica por virus hepatitis B',
            69 => 'Hepatitis C',
            70 => 'Cáncer colorrectal en personas de 15 años y más',
            71 => 'Cáncer de ovario epitelial',
            72 => 'Cáncer vesical en personas de 15 años y más',
            73 => 'Osteosarcoma en personas de 15 años y más',
            74 => 'Tratamiento quirúrgico de lesiones crónicas de la válvula aórtica en personas de 15 años y más',
            75 => 'Trastorno bipolar en personas de 15 años y más',
            76 => 'Hipotiroidismo en personas de 15 años y más',
            77 => 'Tratamiento de hipoacusia moderada en menores de 4 años',
            78 => 'Lupus eritematoso sistémico',
            79 => 'Tratamiento quirúrgico de lesiones crónicas de las válvulas mitral y tricúspide en personas de 15 años y más',
            80 => 'Tratamiento de erradicación del Helicobacter pylori',
            81 => 'Cáncer de pulmón en personas de 15 años y más',
            82 => 'Cáncer de tiroides diferenciado y medular en personas de 15 años y más',
            83 => 'Cáncer renal en personas de 15 años y más',
            84 => 'Mieloma múltiple en personas de 15 años y más',
            85 => 'Enfermedad de Alzheimer y otras demencias',
            86 => 'Atención Integral de Salud en Agresión Sexual Aguda',
            87 => 'Rehabilitación SARS-CoV-2',
            88 => 'Tratamiento Cirrosis tras Alta Hospitalaria',
            89 => 'Tratamiento de Hospitalización para Menores de 15 años con Depresión Grave',
            90 => 'Cesación del Consumo de Tabaco, 25 años y más',
        ];

        foreach ($patologias as $numeroGes => $nombre) {
            $confidencial = in_array($numeroGes, [18, 86], true);

            Patologia::updateOrCreate(
                ['numero_ges' => $numeroGes],
                [
                    'nombre' => $nombre,
                    'descripcion' => $confidencial
                        ? 'Patología GES confidencial. Requiere usuario con autorización correspondiente.'
                        : null,
                    'confidencial' => $confidencial,
                    'activo' => true,
                ],
            );
        }
    }
}
