<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Images placed in form templates (typically the company logo in
 * the header).
 *
 * Uploads go to the private `local` disk -- not `public`, whose storage:link
 * the cPanel deploy does not create -- and are served back through an
 * authenticated route. Template HTML therefore only ever references
 * /forms/images/<file>, which is also the one image source the
 * sanitizer lets through. dompdf cannot follow that route (it has no session),
 * so inlineForPdf() swaps each reference for a data: URI before rendering.
 *
 * Sample templates ship their images under resources/forms/ with a
 * `sample-` prefix; resources/ is deployed, so those resolve on every server
 * without anyone uploading them.
 */
class FormImageService
{
    public const URL_PREFIX = '/forms/images/';

    private const DIRECTORY = 'forms/images';

    private const FILE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}\.(png|jpe?g)$/i';

    public function store(UploadedFile $file): string
    {
        $extension = strtolower($file->extension() === 'jpeg' ? 'jpg' : $file->extension());

        $name = Str::uuid()->toString().'.'.$extension;

        Storage::disk('local')->putFileAs(self::DIRECTORY, $file, $name);

        return self::URL_PREFIX.$name;
    }

    /**
     * Absolute path of a stored or bundled image, or null when there is none.
     */
    public function path(string $file): ?string
    {
        if (! preg_match(self::FILE_PATTERN, $file)) {
            return null;
        }

        if (str_starts_with($file, 'sample-')) {
            $bundled = resource_path('forms/'.$file);

            return is_file($bundled) ? $bundled : null;
        }

        $disk = Storage::disk('local');

        return $disk->exists(self::DIRECTORY.'/'.$file)
            ? $disk->path(self::DIRECTORY.'/'.$file)
            : null;
    }

    /**
     * Accepts the relative form the editor writes and the absolute form a
     * browser may hand back, on any host -- only the path is trusted.
     */
    public static function isOwnUrl(string $src): bool
    {
        $path = (string) parse_url(trim($src), PHP_URL_PATH);

        return str_starts_with($path, self::URL_PREFIX)
            && preg_match(self::FILE_PATTERN, substr($path, strlen(self::URL_PREFIX))) === 1;
    }

    public static function normalizeUrl(string $src): string
    {
        return (string) parse_url(trim($src), PHP_URL_PATH);
    }

    public function inlineForPdf(?string $html): string
    {
        if (! filled($html)) {
            return '';
        }

        return (string) preg_replace_callback(
            '#src="'.preg_quote(self::URL_PREFIX, '#').'([^"]+)"#',
            function (array $match) {
                $path = $this->path($match[1]);

                if (! $path) {
                    return 'src=""';
                }

                $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';

                return 'src="data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path)).'"';
            },
            $html
        );
    }
}
