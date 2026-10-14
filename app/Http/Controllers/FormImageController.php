<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FormImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FormImageController extends Controller
{
    public function __construct(
        private readonly FormImageService $images,
    ) {}

    /**
     * Upload from the template editor. Answers JSON for the editor's fetch
     * call rather than an Inertia redirect.
     */
    public function store(Request $request): JsonResponse
    {
        // Validated by hand: bootstrap/app.php renders exceptions as JSON only
        // for api/*, so $request->validate() would answer this fetch() with a
        // redirect to an HTML page instead of the message.
        $validator = Validator::make($request->all(), [
            // No SVG: it can carry script, and dompdf renders it poorly.
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return response()->json([
            'url' => $this->images->store($request->file('image')),
        ]);
    }

    /**
     * Any signed-in account may read them: header images appear on the form
     * every employee and non-employee is asked to sign.
     */
    public function show(string $file): BinaryFileResponse
    {
        $path = $this->images->path($file);

        abort_unless($path, 404);

        return response()->file($path, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
