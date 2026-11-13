<?php

namespace App\Services\Payments;

/**
 * Resolves gateway drivers from config/payments.php ("gateways.{driver}").
 */
class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGateway>
     */
    private array $resolved = [];

    public function driver(string $name): PaymentGateway
    {
        return $this->resolved[$name] ??= $this->make($name);
    }

    public function has(string $name): bool
    {
        return is_array(config("payments.gateways.{$name}"));
    }

    /**
     * @return list<string>
     */
    public function drivers(): array
    {
        return array_keys((array) config('payments.gateways', []));
    }

    private function make(string $name): PaymentGateway
    {
        $config = config("payments.gateways.{$name}");

        if (! is_array($config)) {
            throw PaymentGatewayException::withErrors(['payment_method_id' => ["Unknown payment gateway “{$name}”."]]);
        }

        return match ($config['class']) {
            FakeGateway::class => new FakeGateway((string) $config['webhook_secret']),
            StripeGateway::class => new StripeGateway($config['secret'] ?? null, $config['webhook_secret'] ?? null, (string) ($config['api_base'] ?? 'https://api.stripe.com/v1'), (int) ($config['tolerance_seconds'] ?? 300)),
            default => app($config['class']),
        };
    }
}
