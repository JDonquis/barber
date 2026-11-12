<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CompanyPwaController extends Controller
{
    public function manifest(Company $company)
    {
        abort_unless($company->active, 404);

        $base = '/reservar/'.$company->slug;

        return response()->json([
            'id' => $base.'/',
            'name' => $company->name,
            'short_name' => mb_substr($company->name, 0, 18),
            'description' => 'Reserva tu cita en '.$company->name,
            'lang' => 'es',
            'dir' => 'ltr',
            'start_url' => $base,
            'scope' => $base,
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f8fafc',
            'theme_color' => '#c01c15',
            'categories' => ['lifestyle', 'business'],
            'icons' => [
                [
                    'src' => route('booking.icon', ['company' => $company->slug, 'size' => 192]),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => route('booking.icon', ['company' => $company->slug, 'size' => 512]),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function icon(Request $request, Company $company, string $size): Response
    {
        abort_unless($company->active, 404);

        $size = in_array($size, ['192', '512'], true) ? (int) $size : 192;
        $logoPath = $company->logo_path
            ? Storage::disk('public')->path($company->logo_path)
            : null;

        if (! $logoPath || ! is_file($logoPath)) {
            $logoPath = public_path('logo.jpeg');
        }

        if (! function_exists('imagecreatetruecolor') || ! is_file($logoPath)) {
            return response()->file(public_path('logo.jpeg'), ['Content-Type' => 'image/jpeg']);
        }

        $canvas = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $source = @imagecreatefromstring((string) @file_get_contents($logoPath));

        if ($source) {
            $width = imagesx($source);
            $height = imagesy($source);
            $target = (int) round($size * 0.72);
            $scale = min($target / max(1, $width), $target / max(1, $height));
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));
            $x = (int) (($size - $newWidth) / 2);
            $y = (int) (($size - $newHeight) / 2);

            imagecopyresampled($canvas, $source, $x, $y, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
        }

        ob_start();
        imagepng($canvas);
        $content = ob_get_clean();
        imagedestroy($canvas);

        return response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
