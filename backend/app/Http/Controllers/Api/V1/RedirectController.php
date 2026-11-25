<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\ResolveRedirectRequest;
use App\Services\SlugRedirectService;
use Illuminate\Http\JsonResponse;

/**
 * Former slugs (SRS 15.3). 200 {data: {type, old_slug, slug, path, status: 301}} when the URL moved, else 404.
 * The website answers the old URL with a 301 to data.path.
 */
class RedirectController extends ApiController
{
    public function __invoke(ResolveRedirectRequest $request, SlugRedirectService $redirects): JsonResponse
    {
        $redirect = $request->filled('path')
            ? $redirects->resolvePath((string) $request->validated('path'))
            : $redirects->resolve((string) $request->validated('type'), (string) $request->validated('slug'), (string) ($request->validated('locale') ?? 'en'));

        return $redirect === null
            ? response()->json(['message' => 'No redirect for this URL.'], 404)
            : response()->json(['data' => $redirect])->header('Cache-Control', 'public, max-age=300');
    }
}
