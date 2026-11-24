<?php

namespace App\Http\Requests\Api;

use App\Services\PartnerStatementExporter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartnerStatementExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['period' => $this->route('period'), 'format' => strtolower((string) $this->query('format', 'pdf'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'format' => ['required', Rule::in(PartnerStatementExporter::FORMATS)],
        ];
    }

    public function exportFormat(): string
    {
        return $this->validated('format');
    }
}
