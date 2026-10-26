<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PrivacyNotice;
use Illuminate\Database\Seeder;

/**
 * Versión vigente del aviso de privacidad para la demo.
 */
class PrivacySeeder extends Seeder
{
    public function run(): void
    {
        PrivacyNotice::firstOrCreate(
            ['version' => '1.0'],
            [
                'content' => 'Aviso de privacidad de demostración de A-Code Solutions. '
                    .'Este texto es un marcador de posición para la expo; sustituir por el '
                    .'aviso legal real antes de un uso en producción.',
                'published_at' => now(),
            ],
        );
    }
}
