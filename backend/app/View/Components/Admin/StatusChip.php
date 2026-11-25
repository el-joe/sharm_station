<?php

namespace App\View\Components\Admin;

use BackedEnum;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Coloured status chip for any status enum (booking, payment, publish, partner, availability).
 */
class StatusChip extends Component
{
    /**
     * Status value => tone.
     *
     * @var array<string, string>
     */
    public const TONES = [
        'awaiting_payment' => 'warning',
        'awaiting_receipt_review' => 'info',
        'proof_rejected' => 'error',
        'deposit_paid' => 'primary',
        'confirmed' => 'success',
        'fully_paid' => 'success',
        'completed' => 'neutral',
        'cancelled' => 'error',
        'refunded' => 'neutral',
        'partially_refunded' => 'info',
        'queued' => 'warning',
        'sent' => 'success',
        'failed' => 'error',
        'published' => 'success',
        'draft' => 'neutral',
        'pending' => 'warning',
        'active' => 'success',
        'suspended' => 'error',
        'inactive' => 'neutral',
        'open' => 'success',
        'closed' => 'neutral',
        'held' => 'warning',
        'booked' => 'primary',
        'hidden' => 'neutral',
        'visible' => 'success',
        'paid' => 'primary',
        'being_arranged' => 'info',
        'succeeded' => 'success',
        'approved' => 'success',
        'declined' => 'error',
        'rejected' => 'error',
        'enabled' => 'success',
        'disabled' => 'neutral',
    ];

    /**
     * @var array<string, string>
     */
    public const CLASSES = [
        'success' => 'border-success/30 bg-success/10 text-success',
        'warning' => 'border-warning/40 bg-warning/10 text-[#8a5f00]',
        'error' => 'border-error/30 bg-error/10 text-error',
        'info' => 'border-info/30 bg-info/10 text-info',
        'primary' => 'border-primary-500/30 bg-primary-100 text-primary-700',
        'neutral' => 'border-ink-300 bg-ink-100 text-ink-700',
    ];

    public string $label;

    public string $classes;

    public function __construct(public BackedEnum|string|null $status, ?string $label = null, ?string $tone = null)
    {
        $value = $status instanceof BackedEnum ? (string) $status->value : (string) $status;
        $this->label = $label
            ?? ($status instanceof BackedEnum && method_exists($status, 'label') ? $status->label() : Str::headline($value));
        $this->classes = self::CLASSES[$tone ?? self::TONES[$value] ?? 'neutral'];
    }

    /**
     * Rendered HTML, for DataTables columns built on the server.
     */
    public static function html(BackedEnum|string|null $status, ?string $label = null, ?string $tone = null): string
    {
        return Blade::renderComponent(new self($status, $label, $tone));
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.status-chip');
    }
}
