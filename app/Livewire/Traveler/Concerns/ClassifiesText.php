<?php
namespace App\Livewire\Traveler\Concerns;

/**
 * Reading what a traveller's message IS — a greeting, a question, profanity,
 * gibberish, a correction, a request for suggestions — without deciding what
 * to do about it. The reply itself stays with the chat flow.
 *
 * Every method here is a pure function of its argument, which is why this was
 * a safe thing to lift out of Llm.php unchanged.
 *
 * One outward dependency: isNonAnswerFiller() reads self::NON_ANSWER_FILLERS,
 * which stays on the component because the slot-filling code uses it too.
 */
trait ClassifiesText
{
    private const GREETINGS = [
        'hi', 'hello', 'hey', 'yo', 'sup', 'hiya',
        'good morning', 'good afternoon', 'good evening', 'good day',
        'how are you', 'how are you doing', "what's up", 'whats up', 'howdy',
    ];

    private const PROFANITY_WORDS = [
        'fuck', 'fucking', 'fucked', 'fucker', 'motherfucker',
        'shit', 'shitty', 'bullshit',
        'bitch', 'bitches',
        'asshole', 'assholes',
        'bastard', 'cunt', 'dumbass', 'douchebag',
    ];

    private const RECOMMEND_TRIGGERS = [
        'recommend', 'suggest', 'you decide', 'you choose', 'surprise me',
        'anywhere', 'no idea', "don't know where", 'dont know where',
        'not sure where', 'not sure', 'pick for me', 'up to you',
        'whatever you think', 'idk', "i don't know", 'dunno',
    ];

    private const CORRECTION_CUES = [
        'actually', 'wait', 'sorry', 'i meant', 'change it', 'change that',
        'make it', 'instead', 'scratch that', 'update it',
    ];

    private function isRecommendationRequest(string $text): bool
    {
        $normalized = strtolower(trim($text, " \t\n\r\0\x0B.!?,"));
        foreach (self::RECOMMEND_TRIGGERS as $trigger) {
            if (str_contains($normalized, $trigger)) return true;
        }
        return false;
    }

    private function looksLikeCorrection(string $text): bool
    {
        $normalized = strtolower($text);
        foreach (self::CORRECTION_CUES as $cue) {
            if (str_contains($normalized, $cue)) return true;
        }
        return false;
    }

    private function isBudgetEnoughQuestion(string $text): bool
    {
        return str_contains($text, '?') && (bool) preg_match('/\benough\b/i', $text);
    }

    private function isGreetingOnly(string $text): bool
    {
        $normalized = strtolower(trim($text, " \t\n\r\0\x0B.!?,"));
        return in_array($normalized, self::GREETINGS, true);
    }

    private function containsProfanity(string $text): bool
    {
        $pattern = '/\b(?:' . implode('|', array_map(fn ($w) => preg_quote($w, '/'), self::PROFANITY_WORDS)) . ')\b/iu';
        return (bool) preg_match($pattern, $text);
    }

    private function isNonAnswerFiller(string $text): bool
    {
        $normalized = strtolower(trim($text, " \t\n\r\0\x0B.!?,"));
        return in_array($normalized, self::NON_ANSWER_FILLERS, true);
    }

    private function looksLikeQuestion(string $text): bool
    {
        $trimmed = trim($text);
        if (str_ends_with($trimmed, '?')) return true;
        return (bool) preg_match('/^(?:is|are|does|do|did|can|could|will|would|should|what|how|why|when|where|who)\b/i', $trimmed);
    }

    private function looksLikeGibberish(string $text): bool
    {
        if (preg_match('/[a-zA-Z]{2,}\d+|\d+[a-zA-Z]{2,}/', $text)) return true;

        if (preg_match('/(.)\1{3,}/i', $text)) return true;

        if (preg_match('/(.{2,4})\1{2,}/i', $text)) return true;

        $nonSpace = preg_replace('/\s+/u', '', $text);
        if ($nonSpace !== '') {
            $letters = preg_replace('/[^\p{L}]/u', '', $nonSpace);
            if (mb_strlen($letters) / mb_strlen($nonSpace) < 0.7) return true;
        }

        return false;
    }
}
