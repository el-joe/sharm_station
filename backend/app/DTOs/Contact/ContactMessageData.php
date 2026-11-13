<?php

namespace App\DTOs\Contact;

use App\Enums\ContactSubject;
use Illuminate\Foundation\Http\FormRequest;

final readonly class ContactMessageData
{
    public function __construct(
        public string $name,
        public string $email,
        public ContactSubject $subject,
        public string $message,
        public ?string $phone = null,
        public ?string $bookingReference = null,
        public string $locale = 'en',
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $data = $request->validated();

        return new self(
            name: $data['name'],
            email: mb_strtolower($data['email']),
            subject: ContactSubject::from($data['subject']),
            message: $data['message'],
            phone: $data['phone'] ?? null,
            bookingReference: isset($data['booking_reference']) ? strtoupper((string) $data['booking_reference']) : null,
            locale: $data['locale'] ?? app()->getLocale(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone, 'subject' => $this->subject,
            'booking_reference' => $this->bookingReference, 'message' => $this->message, 'locale' => $this->locale, 'status' => 'new',
        ];
    }
}
