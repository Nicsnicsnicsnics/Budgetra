<?php
namespace Tests\Feature\Expense;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocr_endpoint_requires_auth(): void
    {
        $this->post(route('expenses.ocr'), [])->assertRedirect(route('login'));
    }

    public function test_ocr_endpoint_returns_json(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Restaurant ABC\nTotal: ₱350.00\nDate: 2026-07-02"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 400, 600),
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['amount', 'date', 'description', 'confidence']);
    }

    public function test_ocr_endpoint_requires_receipt_file(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson(route('expenses.ocr'), []);
        $response->assertStatus(422);
    }

    public function test_ocr_log_is_recorded(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Grocery Store\nTotal: ₱120.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $this->assertDatabaseHas('ocr_logs', ['user_id' => $user->id]);
    }

    // A real phone-camera photo is routinely well over the old 5MB cap,
    // which made every such scan fail validation silently (see below) —
    // bumped to 10MB so ordinary receipt photos actually get through.
    public function test_ocr_endpoint_accepts_receipt_up_to_ten_megabytes(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Store\nTotal: ₱99.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg')->size(8000),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['amount' => 99.0]);
    }

    // Mirrors how the real browser calls this endpoint: an Accept header
    // telling Laravel it's an AJAX request. Without it, a validation
    // failure here returns an HTML redirect instead of JSON, which fetch()
    // can't parse — from the user's side this looked exactly like "nothing
    // happened" when scanning a receipt.
    public function test_ocr_endpoint_returns_json_errors_not_a_redirect_when_receipt_invalid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('expenses.ocr'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('receipt');
    }

    // A receipt with both a Subtotal and Total line — the norm for
    // anything with itemized tax — used to have the parser grab the
    // smaller Subtotal figure instead of the actual amount paid, because
    // "TOTAL" is a literal substring of "SUBTOTAL" and the old regex just
    // took the first match. Confirms it now correctly picks the Total.
    public function test_ocr_prefers_total_over_subtotal_when_receipt_has_both(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Restaurant ABC\nSubtotal: ₱300.00\nTax: ₱36.00\nTotal: ₱336.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertJson(['amount' => 336.0]);
    }

    // The exact bug reported live: a real receipt's total (₱627.00) got
    // extracted as ₱59.00 instead — an individual item's unit price. Real
    // OCR text isn't always read in clean top-to-bottom order (column
    // layouts, keyword misreads, etc.), so grabbing the LAST matched
    // number is fragile whenever the raw text order doesn't line up with
    // the receipt's visual layout. Taking the LARGEST match instead does
    // not depend on order at all — the total is always the biggest single
    // figure printed on a receipt, item prices included.
    public function test_ocr_picks_the_largest_amount_when_matches_are_out_of_order(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' =>
                    "T0TAL ₱627.00\nShrimp Roll ₱189.00\nPho Bo Large ₱379.00\nCanned Mountain Dew ₱59.00"
                ]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertJson(['amount' => 627.0]);
    }

    // Amount parsing only recognized ₱ and $ — receipts from any of this
    // app's other destinations (Tokyo, Seoul, Paris, Bangkok, ...) using
    // ¥/€/£/₩/₫/฿ fell through to no amount at all. Confirms each is now
    // picked up via the bare-symbol fallback path.
    public function test_ocr_recognizes_non_dollar_non_peso_currency_symbols(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Cafe Tokyo\n¥1200"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertJson(['amount' => 1200.0]);
    }

    // When the local regex genuinely can't find any recognizable
    // TOTAL-style keyword or currency symbol at all (a receipt phrased
    // unusually, or OCR noise wiping out every label), it now falls back
    // to asking an AI provider to read the total from context instead of
    // silently giving up with a null amount.
    public function test_ocr_falls_back_to_ai_when_regex_finds_no_amount_at_all(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' =>
                    "Phat Pho Restaurant\nCanned Mountain Dew ... 59\nShrimp Roll ... 185\nPho Bo Large ... 379\nGrand sum for this bill: 627"
                ]],
                'OCRExitCode' => 1,
            ], 200),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['total' => 627])]]],
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertJson(['amount' => 627.0]);
    }

    // The AI fallback must only run when regex found NOTHING — it's an
    // extra API call, so a receipt regex can already parse fine (a plain
    // "Total: ₱350.00") shouldn't pay that latency/cost. No AI provider
    // is faked here at all — if the code tried calling one anyway, this
    // test would hang/fail on a real network call instead of passing fast.
    public function test_ocr_does_not_call_ai_when_regex_already_found_an_amount(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Restaurant ABC\nTotal: ₱350.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        $response->assertJson(['amount' => 350.0]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'mistral.ai'));
    }

    // The scan request now asks OCR.space to auto-detect the receipt's
    // language instead of silently assuming English for every image.
    public function test_ocr_request_asks_for_automatic_language_detection(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [['ParsedText' => "Total: ₱10.00"]],
                'OCRExitCode' => 1,
            ], 200),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expenses.ocr'), [
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ]);

        Http::assertSent(function ($request) {
            return $request['language'] === 'auto';
        });
    }
}
