<?php

namespace App\Http\Middleware;

use App\Services\CurrencyService;
use App\Services\PartnerPricingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies ?locale=en|it and ?currency=EUR|USD|GBP|EGP (or X-Locale / X-Currency headers) and
 * resolves the approved partner, if any, behind the bearer token.
 */
class SetLocaleAndCurrency
{
    public const LOCALES = ['en', 'it'];

    public function __construct(
        private readonly CurrencyService $currencies,
        private readonly PartnerPricingService $partnerPricing,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        $locale = $request->query('locale') ?? $request->header('X-Locale') ?? $request->getPreferredLanguage(self::LOCALES);
        App::setLocale(in_array($locale, self::LOCALES, true) ? $locale : 'en');

        $currency = $request->query('currency') ?? $request->header('X-Currency') ?? $user?->currency ?? CurrencyService::BASE;
        $this->currencies->setCurrent(is_string($currency) ? $currency : CurrencyService::BASE);

        $request->attributes->set('partner', $this->partnerPricing->partnerFor($user));

        return $next($request);
    }
}
