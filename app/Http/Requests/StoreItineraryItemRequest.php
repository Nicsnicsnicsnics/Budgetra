<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItineraryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Trip ownership is checked in the controller (after validation),
        // matching the app's existing pattern of abort_if() ownership checks
        // rather than FormRequest::authorize() — keeps the exact same
        // validate-then-authorize order the app already used everywhere else.
        return true;
    }

    public function rules(): array
    {
        return [
            'trip_id'        => ['required', 'integer', 'exists:trips,id'],
            'title'          => ['required', 'string', 'max:255'],
            'type'           => ['required', 'in:Flight,Hotel,Activity,Transportation'],
            'start_datetime' => ['required', 'date'],
            'end_datetime'   => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'location'       => ['nullable', 'string', 'max:255'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
