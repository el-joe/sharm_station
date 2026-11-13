<?php

use App\Services\CurrencyService;

beforeEach(function () {
    seedCurrencies();
    $this->service = app(CurrencyService::class);
});

test('it converts euro amounts with the stored rate and margin', function () {
    expect($this->service->convert(100, 'EUR'))->toBe(100.0)
        ->and($this->service->convert(100, 'USD'))->toBe(108.0)
        ->and($this->service->convert(19.99, 'EGP'))->toBe(1019.49)
        ->and($this->service->rate('EGP'))->toBe(51.0);
});

test('unsupported currencies fall back to euro', function () {
    $this->service->setCurrent('XYZ');

    expect($this->service->current())->toBe('EUR')
        ->and(fn () => $this->service->convert(10, 'XYZ'))->toThrow(InvalidArgumentException::class);
});

test('money returns the euro amount and the converted amount', function () {
    $this->service->setCurrent('usd');

    expect($this->service->money(50))->toBe(['amount_eur' => 50.0, 'amount' => 54.0, 'currency' => 'USD'])
        ->and($this->service->money(null))->toBeNull();
});
