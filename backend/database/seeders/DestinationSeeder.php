<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            ['sharm-el-sheikh', 'Sharm El Sheikh', 'Sharm El Sheikh', 'Coral reefs, Ras Mohammed and desert adventures on the Sinai coast.', 'Barriere coralline, Ras Mohammed e avventure nel deserto sulla costa del Sinai.'],
            ['hurghada', 'Hurghada', 'Hurghada', 'Red Sea islands, diving and family beaches.', 'Isole del Mar Rosso, immersioni e spiagge per famiglie.'],
            ['luxor', 'Luxor', 'Luxor', 'The world\'s greatest open-air museum: Karnak, Valley of the Kings and the Nile.', 'Il più grande museo a cielo aperto del mondo: Karnak, Valle dei Re e il Nilo.'],
            ['aswan', 'Aswan', 'Assuan', 'Feluccas, Nubian villages and the temple of Philae.', 'Feluche, villaggi nubiani e il tempio di Philae.'],
            ['cairo', 'Cairo', 'Il Cairo', 'The Pyramids of Giza, the Egyptian Museum and Islamic Cairo.', 'Le Piramidi di Giza, il Museo Egizio e il Cairo islamico.'],
            ['marsa-alam', 'Marsa Alam', 'Marsa Alam', 'Untouched reefs, dolphins and the Wadi El Gamal national park.', 'Reef incontaminati, delfini e il parco nazionale di Wadi El Gamal.'],
        ];

        foreach ($destinations as $index => [$slug, $en, $it, $descriptionEn, $descriptionIt]) {
            Destination::query()->updateOrCreate(['slug' => $slug], [
                'name' => ['en' => $en, 'it' => $it],
                'description' => ['en' => $descriptionEn, 'it' => $descriptionIt],
                'image' => '/images/placeholders/destination-'.($index + 1).'.jpg',
                'sort' => $index,
                'is_active' => true,
            ]);
        }
    }
}
