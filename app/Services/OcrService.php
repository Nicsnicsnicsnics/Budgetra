<?php
namespace App\Services;

use App\Models\OcrLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class OcrService
{
    public function scan(UploadedFile $file, int $userId): array
    {
        // This only ever needs the file's BYTES for the API call below, not
        // a permanent copy — storing it here (as this used to) created a
        // second, never-referenced file on every single scan, including
        // ones the traveler never actually saves as an expense. The real
        // receipt copy is stored exactly once, by ExpenseController::store()
        // when the expense is actually created; reading straight from
        // Laravel's own temp upload avoids ever creating the orphan.
        $filename     = $file->getClientOriginalName();
        $apiKey       = config('services.ocr.key');

        if (empty($apiKey)) {
            OcrLog::create([
                'user_id'       => $userId,
                'filename'      => $filename,
                'status'        => 'failed',
                'error_message' => 'OCR API key not configured.',
            ]);
            return ['amount' => null, 'date' => null, 'description' => null, 'category' => null, 'confidence' => 0];
        }

        $imageContent = file_get_contents($file->getRealPath());
        $base64       = 'data:' . $file->getMimeType() . ';base64,' . base64_encode($imageContent);

        // No timeout means this can hang on a slow/unresponsive OCR endpoint
        // until PHP's own execution-time limit kills the request with an
        // uncatchable fatal error — same failure mode fixed elsewhere this
        // session for chained AI-provider calls, just for a single external
        // call here. A bounded timeout throws a (catchable) ConnectionException
        // instead once it's exceeded, which the try/catch below turns into
        // the same graceful "OCR failed" result already used for other
        // failure cases, rather than an unhandled 500.
        try {
            $response = Http::asForm()->timeout(25)->post(config('services.ocr.endpoint'), [
                'apikey'            => $apiKey,
                'base64Image'       => $base64,
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale'             => 'true',
                'OCREngine'         => '2',
                // Engine 2 supports auto language detection, but only for
                // Western Latin-character languages and Chinese — Japanese,
                // Korean, Thai, and Vietnamese aren't in its supported
                // script set regardless of this setting. Without it, the
                // API silently assumes English and does poorly on anything
                // else in the languages it does support.
                'language'          => 'auto',
            ]);
        } catch (\Throwable $e) {
            OcrLog::create([
                'user_id'       => $userId,
                'filename'      => $filename,
                'status'        => 'failed',
                'error_message' => 'OCR request failed: ' . $e->getMessage(),
            ]);
            return ['amount' => null, 'date' => null, 'description' => null, 'category' => null, 'confidence' => 0];
        }

        if (!$response->successful() || ($response->json('OCRExitCode') ?? 0) < 1) {
            OcrLog::create([
                'user_id'       => $userId,
                'filename'      => $filename,
                'status'        => 'failed',
                'error_message' => 'OCR API error: ' . $response->status(),
            ]);
            return ['amount' => null, 'date' => null, 'description' => null, 'category' => null, 'confidence' => 0];
        }

        $text       = $response->json('ParsedResults.0.ParsedText', '');
        $parsed     = $this->parseReceiptText($text);
        $parsed['category'] = $this->guessCategory($text);

        // The regex parser above only ever recognizes a fixed set of
        // keywords/currency symbols — real receipts routinely defeat that
        // with OCR noise (misread labels, scrambled column order, unusual
        // layouts). Only worth the extra API call when regex found
        // NOTHING at all; when it already found a number, that's the
        // same reliable path used for the vast majority of scans, so
        // there's no reason to spend the latency/cost on every request.
        $usedAiFallback = false;
        if ($parsed['amount'] === null) {
            $aiAmount = $this->extractAmountWithAi($text);
            if ($aiAmount !== null) {
                $parsed['amount'] = $aiAmount;
                $usedAiFallback   = true;
            }
        }

        $confidence = $parsed['amount'] ? ($usedAiFallback ? 70.0 : 85.0) : 30.0;

        OcrLog::create([
            'user_id'    => $userId,
            'filename'   => $filename,
            'status'     => $parsed['amount'] ? 'success' : 'partial',
            'confidence' => $confidence,
        ]);

        return array_merge($parsed, ['confidence' => $confidence]);
    }

    private const PROVIDER_ORDER = [
        MistralService::class, OpenRouterService::class, GroqService::class, GeminiService::class,
    ];

    private function tryProviders(\Closure $invoke): mixed
    {
        foreach (self::PROVIDER_ORDER as $class) {
            try {
                $result = $invoke(new $class());
            } catch (\Throwable) {
                continue;
            }
            if ($result) return $result;
        }
        return null;
    }

    // Asks an AI provider to read the raw OCR text and identify the total
    // actually paid — a fallback for exactly the cases the regex parser
    // above can't handle (garbled keywords, scrambled reading order,
    // formats it doesn't recognize), since an LLM can reason about which
    // number is the total from context instead of pattern-matching labels.
    private function extractAmountWithAi(string $text): ?float
    {
        if (trim($text) === '') return null;

        $prompt = <<<PROMPT
        You are reading OCR-scanned text from a receipt. The text may contain
        OCR errors, scrambled line order, or garbled labels.

        Receipt text:
        "{$text}"

        Find the TOTAL amount actually paid — the grand total, not a
        subtotal, not an individual item's price, not a discount or a
        change/tendered amount. If you cannot confidently identify it,
        return null.

        Return JSON only, no markdown:
        {"total": number or null}
        PROMPT;

        $raw = $this->tryProviders(fn ($provider) => $provider->generate($prompt));
        if ($raw === null) return null;

        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw));
        $data  = json_decode($clean, true);

        if (!is_array($data) || !isset($data['total']) || !is_numeric($data['total'])) return null;

        $value = (float) $data['total'];
        return $value > 0 ? $value : null;
    }

    // Keyword => category, checked in order against the full lowercased OCR
    // text. Coarse on purpose — good enough for a pre-filled suggestion the
    // traveler can still change, not meant to be a definitive classifier.
    private const CATEGORY_KEYWORDS = [
        'Transportation'     => ['airline', 'airways', 'grab', 'taxi', 'uber', 'flight', 'bus fare', 'terminal fee', 'toll', 'gasoline', 'gas station', 'fuel', 'parking'],
        'Accommodation'      => ['hotel', 'resort', 'inn', 'hostel', 'lodge', 'suites', 'booking.com', 'airbnb', 'check-in', 'check-out', 'room rate'],
        'Food'                => ['restaurant', 'cafe', 'coffee', 'bar', 'grill', 'diner', 'bakery', 'buffet', 'food court', 'eatery', 'kitchen'],
        'Activities'          => ['tour', 'ticket', 'museum', 'admission', 'entrance fee', 'attraction', 'diving', 'snorkel', 'activity'],
        'Shopping'            => ['mall', 'store', 'shop', 'boutique', 'souvenir', 'market', 'supermarket', 'convenience'],
        'Emergency Expenses' => ['pharmacy', 'hospital', 'clinic', 'drugstore', 'medicine'],
    ];

    private function guessCategory(string $text): ?string
    {
        $haystack = strtolower($text);
        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) return $category;
            }
        }
        return null;
    }

    private function parseReceiptText(string $text): array
    {
        $amount = $date = $description = null;

        // Real receipts print Subtotal, then Tax, then Total, in that
        // reading order, and a bare "TOTAL" pattern also matches inside
        // "SUBTOTAL" itself (it's a literal substring of it) — both
        // branches below used to pick whichever match came LAST in the
        // text, relying on that reading order to land past the subtotal.
        // Taking the LARGEST match instead is strictly more robust: the
        // post-tax total is always >= the pre-tax subtotal, so max()
        // still makes the same correct choice there, but unlike "last
        // match" it doesn't assume OCR preserved left-to-right/top-to-
        // bottom reading order — and it doesn't fall apart when OCR drops
        // a keyword or a currency symbol somewhere it shouldn't. Confirmed
        // live: a real receipt (total ₱627) came back with amount=59 (an
        // item's unit price) under the old "last match" logic — max()
        // correctly returns 627 for the same OCR text.
        // ₱/$ only covered Philippine and US-style receipts — everything
        // else (¥ JPY/CNY, € EUR, £ GBP, ₩ KRW, ₫ VND, ฿ THB) fell through
        // to no match at all. The symbol is often still readable even when
        // OCR garbles surrounding foreign-script text around it, so this
        // helps regardless of whether the "TOTAL"-style keyword above
        // matched (which only recognizes English/French/Spanish wording).
        if (preg_match_all('/(?:GRAND\s*TOTAL|SUB\s*TOTAL|SUBTOTAL|TOTAL|AMOUNT\s*DUE|DUE|AMOUNT)[:\s]*[₱$¥€£₩₫฿]?\s*([\d,]+\.?\d{0,2})/iu', $text, $matches) && !empty($matches[1])) {
            $amount = max(array_map(fn ($v) => (float) str_replace(',', '', $v), $matches[1]));
        } elseif (preg_match_all('/[₱$¥€£₩₫฿]\s*([\d,]+\.?\d{0,2})/u', $text, $matches) && !empty($matches[1])) {
            $amount = max(array_map(fn ($v) => (float) str_replace(',', '', $v), $matches[1]));
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $m)) {
            $date = $m[1];
        } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $text, $m)) {
            $date = "{$m[3]}-" . str_pad($m[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
        }

        $lines       = array_filter(array_map('trim', explode("\n", $text)));
        $description = reset($lines) ?: null;

        return compact('amount', 'date', 'description');
    }
}
