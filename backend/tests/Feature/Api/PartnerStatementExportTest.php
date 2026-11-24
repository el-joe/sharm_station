<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Partner;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));
    $this->partner = Partner::factory()->create(['company' => 'Viaggi Sole']);
    Booking::factory()->create(['reference' => 'BK-5001', 'partner_id' => $this->partner->id, 'retail_total' => 100, 'total' => 90, 'partner_total' => 90, 'commission_amount' => 10, 'lead_name' => 'Mario Bianchi']);
    Booking::factory()->create(['reference' => 'BK-5002', 'partner_id' => $this->partner->id, 'status' => BookingStatus::Cancelled]);
    Booking::factory()->create(['reference' => 'BK-5003', 'partner_id' => Partner::factory()->create()->id]);
});

test('statements link to their PDF and Excel exports', function () {
    Sanctum::actingAs($this->partner->user);

    $this->getJson('/api/v1/partner/statements')
        ->assertOk()
        ->assertJsonPath('data.0.pdf_url', route('api.v1.partner.statements.export', ['period' => '2026-11', 'format' => 'pdf']))
        ->assertJsonPath('data.0.excel_url', route('api.v1.partner.statements.export', ['period' => '2026-11', 'format' => 'xlsx']));
});

test('the PDF statement is a real PDF file', function () {
    Sanctum::actingAs($this->partner->user);

    $response = $this->get('/api/v1/partner/statements/2026-11/export?format=pdf')->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('statement-2026-11-viaggi-sole.pdf')
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

test('the Excel statement holds only the partner own, non-cancelled bookings', function () {
    Sanctum::actingAs($this->partner->user);

    $response = $this->get('/api/v1/partner/statements/2026-11/export?format=xlsx')->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->streamedContent());
    $sheet = IOFactory::load($path)->getActiveSheet()->toArray();
    unlink($path);

    $references = array_column(array_slice($sheet, 6), 0);
    expect($references)->toContain('BK-5001')->not->toContain('BK-5002')->not->toContain('BK-5003')
        ->and($sheet[1][1])->toBe('Viaggi Sole')
        ->and(end($sheet)[0])->toBe('Totals');
});

test('exports need an approved partner, a valid period and format', function () {
    $this->getJson('/api/v1/partner/statements/2026-11/export?format=pdf')->assertUnauthorized();

    Sanctum::actingAs(Partner::factory()->pending()->create()->user);
    $this->getJson('/api/v1/partner/statements/2026-11/export?format=pdf')->assertForbidden();

    Sanctum::actingAs($this->partner->user->fresh());
    $this->getJson('/api/v1/partner/statements/2026-11/export?format=docx')->assertUnprocessable()->assertJsonValidationErrors('format');
    $this->getJson('/api/v1/partner/statements/2027-05/export?format=pdf')->assertUnprocessable()->assertJsonValidationErrors('period');
    $this->getJson('/api/v1/partner/statements/2026-13/export?format=pdf')->assertUnprocessable();
});
