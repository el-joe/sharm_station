<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Proof of a manual payment (SRS 10.3): 1 to 3 files, JPG/PNG/WebP/PDF (real content type), 5 MB each.
 */
class StorePaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = (int) config('payments.proofs.max_files', 3);

        return [
            'token' => ['nullable', 'string', 'max:100'],
            'payment_method_id' => ['required', 'integer'],
            'files' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'files.*' => [
                'required',
                'file',
                'max:'.(int) config('payments.proofs.max_kilobytes', 5120),
                'mimetypes:'.implode(',', (array) config('payments.proofs.mimetypes')),
                'extensions:jpg,jpeg,png,webp,pdf',
            ],
            'transaction_number' => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'sender_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.mimetypes' => 'Upload a JPG, PNG, WebP or PDF file.',
            'files.*.extensions' => 'Upload a JPG, PNG, WebP or PDF file.',
            'files.*.max' => 'Each file can be at most 5 MB.',
            'files.max' => 'Upload at most :max files.',
        ];
    }
}
