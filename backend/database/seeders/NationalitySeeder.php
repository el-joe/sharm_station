<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Locale;
use ResourceBundle;

/**
 * ISO 3166-1 countries (names in EN/IT from PHP intl) and the four standard nationality groups (CR-10).
 * Idempotent: existing groups and admin country assignments are kept.
 */
class NationalitySeeder extends Seeder
{
    /**
     * ICU region codes that are not ISO 3166-1 countries.
     */
    private const NOT_COUNTRIES = ['AC', 'CP', 'CQ', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'XK', 'ZZ'];

    /**
     * @var array<string, array{name: array{en: string, it: string}, sort: int, default: bool, countries: list<string>}>
     */
    public const GROUPS = [
        'egyptian' => ['name' => ['en' => 'Egyptian', 'it' => 'Egiziani'], 'sort' => 1, 'default' => false, 'countries' => ['EG']],
        'arab_countries' => ['name' => ['en' => 'Arab countries', 'it' => 'Paesi arabi'], 'sort' => 2, 'default' => false, 'countries' => [
            'SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'JO', 'LB', 'MA', 'TN', 'DZ', 'IQ', 'LY', 'SD', 'YE', 'PS', 'SY',
        ]],
        'europe' => ['name' => ['en' => 'Europe', 'it' => 'Europa'], 'sort' => 3, 'default' => false, 'countries' => [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB', 'CH', 'NO',
        ]],
        'rest_of_world' => ['name' => ['en' => 'Rest of world', 'it' => 'Resto del mondo'], 'sort' => 4, 'default' => true, 'countries' => []],
    ];

    public function run(): void
    {
        $now = now();
        $this->seedCountries($now);

        $hasDefault = DB::table('nationality_groups')->where('is_default', true)->exists();

        foreach (self::GROUPS as $code => $group) {
            $id = DB::table('nationality_groups')->where('code', $code)->value('id');

            if ($id === null) {
                $id = DB::table('nationality_groups')->insertGetId([
                    'code' => $code,
                    'name' => json_encode($group['name']),
                    'is_default' => $group['default'] && ! $hasDefault,
                    'sort' => $group['sort'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('countries')->whereIn('code', $group['countries'])->whereNull('nationality_group_id')->update(['nationality_group_id' => $id]);
        }
    }

    /**
     * @return array<string, array{en: string, it: string}>
     */
    public static function countryNames(): array
    {
        $names = [];
        $regions = ResourceBundle::create('en', 'ICUDATA-region')?->get('Countries');

        foreach ($regions ?? [] as $code => $name) {
            if (! is_string($code) || preg_match('/^[A-Z]{2}$/', $code) !== 1 || in_array($code, self::NOT_COUNTRIES, true)) {
                continue;
            }

            $names[$code] = [
                'en' => Locale::getDisplayRegion('-'.$code, 'en'),
                'it' => Locale::getDisplayRegion('-'.$code, 'it'),
            ];
        }

        return $names;
    }

    private function seedCountries(\DateTimeInterface $now): void
    {
        $names = self::countryNames();
        uasort($names, fn (array $a, array $b): int => strcmp($a['en'], $b['en']));
        $existing = DB::table('countries')->pluck('code')->all();
        $rows = [];
        $sort = 0;

        foreach ($names as $code => $name) {
            $sort++;

            if (in_array($code, $existing, true)) {
                continue;
            }

            $rows[] = ['code' => $code, 'name' => json_encode($name), 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('countries')->insert($chunk);
        }
    }
}
