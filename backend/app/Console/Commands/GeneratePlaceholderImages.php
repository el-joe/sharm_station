<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Writes a simple generated JPG (GD, no download) for every "/images/placeholders/*.jpg" path stored in the
 * database, so seeded image paths resolve on the API origin (e.g. http://localhost:8090/images/placeholders/trip-1.jpg).
 */
#[Signature('media:placeholders {--force : Overwrite existing files}')]
#[Description('Generate placeholder JPGs in public/images/placeholders for every seeded image path')]
class GeneratePlaceholderImages extends Command
{
    /**
     * @var array<string, list<string>>
     */
    public const COLUMNS = [
        'trips' => ['cover_image'], 'trip_media' => ['path'], 'trip_timeline_items' => ['image'], 'packages' => ['cover_image'],
        'buildings' => ['cover_image'], 'rooms' => ['cover_image'], 'destinations' => ['image'], 'addons' => ['image'], 'vehicle_types' => ['image'],
    ];

    public const PREFIX = '/images/placeholders/';

    public function handle(): int
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->error('The GD extension is required.');

            return self::FAILURE;
        }

        $paths = collect(self::COLUMNS)
            ->flatMap(fn (array $columns, string $table) => collect($columns)->flatMap(fn (string $column) => DB::table($table)->whereNotNull($column)->pluck($column)))
            ->filter(fn ($path): bool => is_string($path) && str_starts_with($path, self::PREFIX) && str_ends_with($path, '.jpg'))
            ->unique()
            ->values();

        File::ensureDirectoryExists(public_path('images/placeholders'));
        $created = 0;

        foreach ($paths as $path) {
            $file = public_path(ltrim($path, '/'));

            if (! $this->option('force') && File::exists($file)) {
                continue;
            }

            $this->write($file, Str::of(basename($path, '.jpg'))->replace('-', ' ')->title()->toString());
            $created++;
        }

        $this->info("{$created} placeholder images written ({$paths->count()} referenced).");

        return self::SUCCESS;
    }

    private function write(string $file, string $label): void
    {
        $width = 1200;
        $height = 800;
        $image = imagecreatetruecolor($width, $height);
        $hue = crc32($label) % 360;
        [$red, $green, $blue] = $this->hsl($hue, 0.45, 0.42);
        [$red2, $green2, $blue2] = $this->hsl(($hue + 40) % 360, 0.55, 0.62);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $color = imagecolorallocate($image, (int) ($red + ($red2 - $red) * $ratio), (int) ($green + ($green2 - $green) * $ratio), (int) ($blue + ($blue2 - $blue) * $ratio));
            imageline($image, 0, $y, $width, $y, $color);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $scale = 6;
        $text = imagecreatetruecolor(imagefontwidth(5) * strlen($label), imagefontheight(5));
        imagefill($text, 0, 0, imagecolorallocatealpha($text, 0, 0, 0, 127));
        imagesavealpha($text, true);
        imagestring($text, 5, 0, 0, $label, $white);
        $textWidth = imagesx($text) * $scale;
        $textHeight = imagesy($text) * $scale;
        imagecopyresized($image, $text, (int) (($width - $textWidth) / 2), (int) (($height - $textHeight) / 2), 0, 0, $textWidth, $textHeight, imagesx($text), imagesy($text));

        imagejpeg($image, $file, 80);
        imagedestroy($text);
        imagedestroy($image);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hsl(int $hue, float $saturation, float $lightness): array
    {
        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $x = $chroma * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $lightness - $chroma / 2;
        [$r, $g, $b] = match (intdiv($hue, 60)) {
            0 => [$chroma, $x, 0], 1 => [$x, $chroma, 0], 2 => [0, $chroma, $x], 3 => [0, $x, $chroma], 4 => [$x, 0, $chroma], default => [$chroma, 0, $x],
        };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
