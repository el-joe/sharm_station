<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Email events of SRS 15.1. Default subject, body and button text live in lang/{locale}/notifications.php
 * and are seeded into notification_templates, where the admin can edit them.
 */
enum NotificationEvent: string
{
    use HasOptions;

    case BookingCreated = 'booking_created';
    case BookingCreatedAdmin = 'booking_created_admin';
    case DepositConfirmed = 'deposit_confirmed';
    case DepositConfirmedAdmin = 'deposit_confirmed_admin';
    case BalanceReminder = 'balance_reminder';
    case BalanceRecorded = 'balance_recorded';
    case BalanceOverdueAdmin = 'balance_overdue_admin';
    case PaymentFailed = 'payment_failed';
    case BookingExpired = 'booking_expired';
    case HoldExpiring = 'hold_expiring';
    case TransferRequestedAdmin = 'transfer_requested_admin';
    case TransferStatusChanged = 'transfer_status_changed';
    case IncludedTransferAdmin = 'included_transfer_admin';
    case ProofUploadedAdmin = 'proof_uploaded_admin';
    case ProofApproved = 'proof_approved';
    case ProofRejected = 'proof_rejected';
    case CancellationRequestedAdmin = 'cancellation_requested_admin';
    case CancellationApproved = 'cancellation_approved';
    case CancellationDeclined = 'cancellation_declined';
    case RefundIssued = 'refund_issued';
    case PartnerApplicationAdmin = 'partner_application_admin';
    case PartnerApproved = 'partner_approved';
    case PartnerRejected = 'partner_rejected';
    case ReviewInvitation = 'review_invitation';
    case PartnerStatement = 'partner_statement';
    case CustomerWelcome = 'customer_welcome';
    case PasswordReset = 'password_reset';

    public const BOOKING_PLACEHOLDERS = [
        'reference', 'customer_name', 'service', 'service_date', 'total', 'deposit', 'paid', 'balance', 'currency',
        'booking_url', 'status', 'payment_due_at', 'balance_due_on',
    ];

    public const ACCOUNT_PLACEHOLDERS = ['customer_name', 'customer_email', 'password_url', 'account_url'];

    public const COMPANY_PLACEHOLDERS = ['company_name', 'company_email', 'company_phone'];

    public function label(): string
    {
        return match ($this) {
            self::BookingCreated => 'New booking',
            self::BookingCreatedAdmin => 'New booking (admin)',
            self::DepositConfirmed => 'Payment confirmed (deposit)',
            self::DepositConfirmedAdmin => 'Payment confirmed (admin)',
            self::BalanceReminder => 'Balance reminder',
            self::BalanceRecorded => 'Balance recorded, account closed',
            self::BalanceOverdueAdmin => 'Overdue balances (admin)',
            self::PaymentFailed => 'Payment failed',
            self::BookingExpired => 'Payment deadline expired',
            self::HoldExpiring => 'Accommodation hold about to expire',
            self::TransferRequestedAdmin => 'Transfer request (admin)',
            self::TransferStatusChanged => 'Transfer status change',
            self::IncludedTransferAdmin => 'Included transfer task (admin)',
            self::ProofUploadedAdmin => 'New proof uploaded (admin)',
            self::ProofApproved => 'Proof approved',
            self::ProofRejected => 'Proof rejected',
            self::CancellationRequestedAdmin => 'Cancellation request (admin)',
            self::CancellationApproved => 'Cancellation approved',
            self::CancellationDeclined => 'Cancellation declined',
            self::RefundIssued => 'Refund issued',
            self::PartnerApplicationAdmin => 'Partner application received (admin)',
            self::PartnerApproved => 'Partner application approved',
            self::PartnerRejected => 'Partner application rejected',
            self::ReviewInvitation => 'Post-trip Google review invitation',
            self::PartnerStatement => 'Partner monthly statement',
            self::CustomerWelcome => 'Welcome, set your password (account created by staff)',
            self::PasswordReset => 'Password reset link',
        };
    }

    public function audience(): NotificationAudience
    {
        return match ($this) {
            self::BookingCreatedAdmin, self::DepositConfirmedAdmin, self::BalanceOverdueAdmin, self::TransferRequestedAdmin,
            self::IncludedTransferAdmin, self::ProofUploadedAdmin, self::CancellationRequestedAdmin, self::PartnerApplicationAdmin => NotificationAudience::Admin,
            self::PartnerApproved, self::PartnerRejected, self::PartnerStatement => NotificationAudience::Partner,
            default => NotificationAudience::Customer,
        };
    }

    /**
     * The booking confirmation and the "account closed" email carry the voucher PDF.
     */
    public function attachesVoucher(): bool
    {
        return in_array($this, [self::DepositConfirmed, self::BalanceRecorded], true);
    }

    /**
     * Whether the event is about one booking (booking placeholders are available).
     */
    public function isBookingEvent(): bool
    {
        return ! in_array($this, [self::BalanceOverdueAdmin, self::PartnerApplicationAdmin, self::PartnerApproved, self::PartnerRejected, self::PartnerStatement,
            self::CustomerWelcome, self::PasswordReset], true);
    }

    /**
     * Whether the event is about a website account (account placeholders, password link).
     */
    public function isAccountEvent(): bool
    {
        return in_array($this, [self::CustomerWelcome, self::PasswordReset], true);
    }

    /**
     * Password broker (config auth.passwords) that issues the token of the password link of an account event.
     */
    public function passwordBroker(): ?string
    {
        return match ($this) {
            self::CustomerWelcome => 'welcome',
            self::PasswordReset => 'users',
            default => null,
        };
    }

    /**
     * Placeholder that holds the URL of the email button.
     */
    public function actionPlaceholder(): ?string
    {
        return match (true) {
            $this === self::ReviewInvitation => 'review_url',
            $this === self::PartnerStatement => 'statements_url',
            $this === self::PartnerApproved => 'partner_panel_url',
            $this === self::PartnerRejected => null,
            $this->isAccountEvent() => 'password_url',
            $this->audience() === NotificationAudience::Admin => 'admin_url',
            default => 'booking_url',
        };
    }

    /**
     * Placeholders the template may use, e.g. {{reference}}.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        $extra = match ($this) {
            self::BookingCreatedAdmin, self::DepositConfirmedAdmin, self::ProofUploadedAdmin, self::CancellationRequestedAdmin => ['admin_url', 'partner_company', 'reason'],
            self::BalanceOverdueAdmin => ['admin_url', 'overdue_count', 'overdue_list'],
            self::TransferRequestedAdmin, self::IncludedTransferAdmin => ['admin_url', 'transfer_details'],
            self::TransferStatusChanged => ['transfer_status', 'transfer_details', 'driver_name', 'driver_phone', 'car_plate'],
            self::HoldExpiring => ['hold_expires_at'],
            self::ProofRejected, self::CancellationDeclined => ['reason'],
            self::CancellationApproved, self::RefundIssued => ['refund_amount'],
            self::ReviewInvitation => ['review_url'],
            self::PartnerApplicationAdmin => ['admin_url', 'partner_company', 'partner_contact', 'partner_email', 'partner_phone', 'partner_country'],
            self::PartnerApproved => ['partner_company', 'partner_contact', 'partner_panel_url'],
            self::PartnerRejected => ['partner_company', 'partner_contact', 'reason'],
            self::PartnerStatement => ['partner_company', 'partner_contact', 'period', 'statement_bookings', 'statement_total', 'statement_commission', 'statement_balance', 'statements_url'],
            self::CustomerWelcome, self::PasswordReset => self::ACCOUNT_PLACEHOLDERS,
            default => [],
        };

        $base = $this->isBookingEvent() ? self::BOOKING_PLACEHOLDERS : [];

        return array_values(array_unique([...$base, ...$extra, ...self::COMPANY_PLACEHOLDERS]));
    }
}
