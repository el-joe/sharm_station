<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What a back-office user did (activity log).
 */
enum ActivityAction: string
{
    use HasOptions;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case StatusChanged = 'status_changed';
    case PaymentRecorded = 'payment_recorded';
    case ProofApproved = 'proof_approved';
    case ProofRejected = 'proof_rejected';
    case PartnerApproved = 'partner_approved';
    case PartnerSuspended = 'partner_suspended';
    case PartnerRejected = 'partner_rejected';
    case SuggestionApproved = 'suggestion_approved';
    case SuggestionRejected = 'suggestion_rejected';
    case CommissionsChanged = 'commissions_changed';
    case CancellationApproved = 'cancellation_approved';
    case CancellationDeclined = 'cancellation_declined';
    case Activated = 'activated';
    case Deactivated = 'deactivated';
    case PasswordChanged = 'password_changed';
    case NotesUpdated = 'notes_updated';
    case RefundRecorded = 'refund_recorded';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case NotificationResent = 'notification_resent';
    case BookingCreated = 'booking_created';
    case BookingModified = 'booking_modified';
    case HoldReleased = 'hold_released';
    case CalendarSynced = 'calendar_synced';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::StatusChanged => 'Status changed',
            self::PaymentRecorded => 'Payment recorded',
            self::ProofApproved => 'Proof approved',
            self::ProofRejected => 'Proof rejected',
            self::PartnerApproved => 'Partner approved',
            self::PartnerSuspended => 'Partner suspended',
            self::PartnerRejected => 'Partner rejected',
            self::SuggestionApproved => 'Suggestion approved',
            self::SuggestionRejected => 'Suggestion rejected',
            self::CommissionsChanged => 'Commissions changed',
            self::CancellationApproved => 'Cancellation approved',
            self::CancellationDeclined => 'Cancellation declined',
            self::Activated => 'Activated',
            self::Deactivated => 'Deactivated',
            self::PasswordChanged => 'Password changed',
            self::NotesUpdated => 'Notes updated',
            self::RefundRecorded => 'Refund recorded',
            self::TwoFactorEnabled => 'Two-factor enabled',
            self::TwoFactorDisabled => 'Two-factor disabled',
            self::NotificationResent => 'Email resent',
            self::BookingCreated => 'Booking created',
            self::BookingModified => 'Booking modified',
            self::HoldReleased => 'Hold released',
            self::CalendarSynced => 'Calendar synced',
        };
    }
}
