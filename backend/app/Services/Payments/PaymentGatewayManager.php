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
        // Keyed by the driver config too, so a changed configuration (tests, long-running workers) is picked up.
        return $this->resolved[$name.'|'.md5((string) json_encode(config("payments.gateways.{$name}")))] ??= $this->make($name);
    }

    /**
     * Whether the driver exists in the configuration (it may still lack its keys).
     */
    public function has(string $name): bool
    {
        return is_array(config("payments.gateways.{$name}"));
    }

    /**
     * Whether the driver exists and has its keys: only these are offered to customers.
     */
    public function isAvailable(?string $name): bool
    {
        return $name !== null && $this->has($name) && $this->driver($name)->isConfigured();
    }

    /**
     * @return list<string>
     */
    public function drivers(): array
    {
        return array_keys((array) config('payments.gateways', []));
    }

    /**
     * Driver => configured, for the admin payment methods screen.
     *
     * @return array<string, bool>
     */
    public function status(): array
    {
        return collect($this->drivers())->mapWithKeys(fn (string $driver): array => [$driver => $this->isAvailable($driver)])->all();
    }

    public function forget(): void
    {
        $this->resolved = [];
    }

    private function make(string $name): PaymentGateway
    {
        $config = config("payments.gateways.{$name}");

        if (! is_array($config)) {
            throw PaymentGatewayException::withErrors(['payment_method_id' => ["Unknown payment gateway “{$name}”."]]);
        }

        return match ($config['class']) {
            FakeGateway::class => new FakeGateway((string) $config['webhook_secret'], (bool) ($config['enabled'] ?? true)),
            StripeGateway::class => new StripeGateway($config['secret'] ?? null, $config['webhook_secret'] ?? null, $config['api_base'] ?? null, (int) ($config['tolerance_seconds'] ?? 300)),
            PayPalGateway::class => new PayPalGateway($config['client_id'] ?? null, $config['client_secret'] ?? null, (string) ($config['mode'] ?? 'sandbox'), $config['webhook_id'] ?? null),
            default => app($config['class']),
        };
    }
}
