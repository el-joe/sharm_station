<?php

/*
| Default email templates (SRS 15.1). Seeded into notification_templates, where the admin edits them.
| Placeholders: {{name}} (see NotificationEvent::placeholders()). Blank lines separate paragraphs, **text** is bold.
*/

return [
    'layout' => [
        'tagline' => 'Trips, Nile cruises and stays in Sharm El Sheikh',
        'footer' => 'You receive this email about your booking with :company.',
        'footer_admin' => 'Back-office notification from :company.',
        'footer_account' => 'You receive this email about your account with :company.',
        'button_fallback' => 'If the button does not work, copy this link into your browser:',
        'voucher_attached' => 'Your voucher is attached to this email as a PDF.',
    ],

    'events' => [
        'booking_created' => [
            'subject' => 'Booking {{reference}} received',
            'body' => "Hello {{customer_name}},\n\nthank you for booking **{{service}}** on {{service_date}} with {{company_name}}.\n\nTotal: **{{total}}**\nDeposit due now: **{{deposit}}**\nBalance later: {{balance}}\n\nPlease complete the payment by {{payment_due_at}}, otherwise the booking expires.",
            'action' => 'Complete your payment',
        ],
        'booking_created_admin' => [
            'subject' => 'New booking {{reference}} · {{service}}',
            'body' => "A new booking has been created.\n\nReference: **{{reference}}**\nService: {{service}} on {{service_date}}\nBooker: {{customer_name}} {{partner_company}}\nTotal: {{total}} · deposit {{deposit}}\nStatus: {{status}}",
            'action' => 'Open the booking',
        ],
        'deposit_confirmed' => [
            'subject' => 'Booking {{reference}} confirmed',
            'body' => "Hello {{customer_name}},\n\nwe have received your payment: booking **{{reference}}** for **{{service}}** on {{service_date}} is confirmed.\n\nPaid: **{{paid}}**\nBalance due: {{balance}} (by {{balance_due_on}})\n\nYour voucher is attached. See you soon!",
            'action' => 'View your booking',
        ],
        'deposit_confirmed_admin' => [
            'subject' => 'Payment received for {{reference}}',
            'body' => "The deposit of booking **{{reference}}** has been paid.\n\nService: {{service}} on {{service_date}}\nBooker: {{customer_name}} {{partner_company}}\nPaid: {{paid}} · balance {{balance}}",
            'action' => 'Open the booking',
        ],
        'balance_reminder' => [
            'subject' => 'Reminder: balance of booking {{reference}}',
            'body' => "Hello {{customer_name}},\n\na friendly reminder that the balance of **{{balance}}** for **{{service}}** on {{service_date}} is due by **{{balance_due_on}}**.\n\nIf you have already paid, please ignore this email.",
            'action' => 'View your booking',
        ],
        'balance_recorded' => [
            'subject' => 'Booking {{reference}} fully paid',
            'body' => "Hello {{customer_name}},\n\nwe have recorded your balance payment. Booking **{{reference}}** is now fully paid ({{paid}}) and your account is closed.\n\nYour updated voucher is attached.",
            'action' => 'View your booking',
        ],
        'balance_overdue_admin' => [
            'subject' => '{{overdue_count}} bookings with an overdue balance',
            'body' => "These bookings still have a balance to collect after the due date:\n\n{{overdue_list}}",
            'action' => 'Open bookings',
        ],
        'payment_failed' => [
            'subject' => 'Payment for booking {{reference}} failed',
            'body' => "Hello {{customer_name}},\n\nthe online payment for booking **{{reference}}** did not go through. No money has been taken.\n\nYou can try again or choose another payment method before {{payment_due_at}}.",
            'action' => 'Try again',
        ],
        'booking_expired' => [
            'subject' => 'Booking {{reference}} expired',
            'body' => "Hello {{customer_name}},\n\nwe did not receive the payment for booking **{{reference}}** ({{service}}, {{service_date}}) before the deadline, so the booking has expired.\n\nYou are welcome to book again on our website.",
            'action' => 'View the booking',
        ],
        'hold_expiring' => [
            'subject' => 'Your room hold for {{reference}} expires soon',
            'body' => "Hello {{customer_name}},\n\nwe are holding **{{service}}** for you until **{{hold_expires_at}}**. Complete the payment of {{deposit}} before then to keep the room.",
            'action' => 'Pay now',
        ],
        'transfer_requested_admin' => [
            'subject' => 'Transfer request {{reference}}',
            'body' => "A transfer has been requested with booking **{{reference}}**.\n\n{{transfer_details}}\n\nBooker: {{customer_name}}\nPrice: {{total}} · status {{status}}",
            'action' => 'Open the booking',
        ],
        'transfer_status_changed' => [
            'subject' => 'Transfer {{reference}}: {{transfer_status}}',
            'body' => "Hello {{customer_name}},\n\nthe status of your transfer is now **{{transfer_status}}**.\n\n{{transfer_details}}\nDriver: {{driver_name}} {{driver_phone}}\nCar plate: {{car_plate}}",
            'action' => 'View your booking',
        ],
        'included_transfer_admin' => [
            'subject' => 'Included transfers to arrange for {{reference}}',
            'body' => "Package booking **{{reference}}** includes transfers to arrange:\n\n{{transfer_details}}",
            'action' => 'Open the booking',
        ],
        'proof_uploaded_admin' => [
            'subject' => 'New proof of payment for {{reference}}',
            'body' => "A proof of payment has been uploaded for booking **{{reference}}** ({{customer_name}}).\n\nPlease review it in the receipts queue.",
            'action' => 'Review the receipt',
        ],
        'proof_approved' => [
            'subject' => 'Payment received for booking {{reference}}',
            'body' => "Hello {{customer_name}},\n\nyour proof of payment has been approved. Paid so far: **{{paid}}**, balance {{balance}}.",
            'action' => 'View your booking',
        ],
        'proof_rejected' => [
            'subject' => 'Proof of payment for {{reference}} not accepted',
            'body' => "Hello {{customer_name}},\n\nwe could not accept the proof of payment for booking **{{reference}}**.\n\nReason: {{reason}}\n\nPlease upload a new proof from the booking page.",
            'action' => 'Upload a new proof',
        ],
        'cancellation_requested_admin' => [
            'subject' => 'Cancellation request for {{reference}}',
            'body' => "The booker asked to cancel booking **{{reference}}** ({{service}}, {{service_date}}).\n\nReason: {{reason}}\nPaid: {{paid}}",
            'action' => 'Decide the request',
        ],
        'cancellation_approved' => [
            'subject' => 'Booking {{reference}} cancelled',
            'body' => "Hello {{customer_name}},\n\nyour cancellation request has been approved and booking **{{reference}}** is cancelled.\n\nRefund: **{{refund_amount}}**. You will receive a separate email when it is paid.",
            'action' => 'View your booking',
        ],
        'cancellation_declined' => [
            'subject' => 'Cancellation request for {{reference}} declined',
            'body' => "Hello {{customer_name}},\n\nwe could not accept the cancellation of booking **{{reference}}**.\n\nReason: {{reason}}\n\nThe booking stays active. Reply to this email or send a new request if anything changes.",
            'action' => 'View your booking',
        ],
        'refund_issued' => [
            'subject' => 'Refund for booking {{reference}}',
            'body' => "Hello {{customer_name}},\n\nwe have refunded **{{refund_amount}}** for booking **{{reference}}**. Depending on your bank it can take a few days to appear.",
            'action' => 'View your booking',
        ],
        'partner_application_admin' => [
            'subject' => 'New partner application: {{partner_company}}',
            'body' => "A new partner application is waiting for review.\n\nCompany: **{{partner_company}}**\nContact: {{partner_contact}} · {{partner_email}} · {{partner_phone}}\nCountry: {{partner_country}}",
            'action' => 'Review the application',
        ],
        'partner_approved' => [
            'subject' => 'Welcome to {{company_name}} partners',
            'body' => "Hello {{partner_contact}},\n\nthe partner account of **{{partner_company}}** has been approved. You can now book at partner prices from the partner panel.",
            'action' => 'Open the partner panel',
        ],
        'partner_rejected' => [
            'subject' => 'Your partner application',
            'body' => "Hello {{partner_contact}},\n\nthank you for your interest. We cannot approve the partner application of **{{partner_company}}** at this time.\n\nReason: {{reason}}",
            'action' => '',
        ],
        'review_invitation' => [
            'subject' => 'How was {{service}}?',
            'body' => "Hello {{customer_name}},\n\nthank you for travelling with {{company_name}}! We hope you enjoyed **{{service}}**.\n\nWould you take a minute to share your experience on Google? It helps other travellers and means a lot to our team.",
            'action' => 'Write a review on Google',
        ],
        'partner_statement' => [
            'subject' => 'Your statement for {{period}}',
            'body' => "Hello {{partner_contact}},\n\nhere is the statement of **{{partner_company}}** for {{period}}.\n\nBookings: {{statement_bookings}}\nPartner total: **{{statement_total}}**\nYour commission: {{statement_commission}}\nBalance due: **{{statement_balance}}**\n\nThe PDF is attached.",
            'action' => 'Open your statements',
        ],
        'customer_welcome' => [
            'subject' => 'Welcome to {{company_name}}: set your password',
            'body' => "Hello {{customer_name}},\n\nwe have created your {{company_name}} account for **{{customer_email}}**, so you can follow your bookings online.\n\nChoose your password with the button below. The link is personal and expires in 7 days; after that, use \"Forgot password\" on the sign-in page.",
            'action' => 'Set your password',
        ],
        'password_reset' => [
            'subject' => 'Reset your {{company_name}} password',
            'body' => "Hello {{customer_name}},\n\nwe received a request to reset the password of your account **{{customer_email}}**.\n\nChoose a new password with the button below. The link expires in 60 minutes. If you did not ask for it, ignore this email: your password stays the same.",
            'action' => 'Reset your password',
        ],
    ],
];
