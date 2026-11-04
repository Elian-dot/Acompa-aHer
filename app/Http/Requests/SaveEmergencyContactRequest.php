<?php

namespace App\Http\Requests;

use App\Models\EmergencyContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        Gate::authorize($contact instanceof EmergencyContact ? 'update' : 'create', $contact instanceof EmergencyContact ? $contact : EmergencyContact::class);

        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:254'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.regex' => 'Usa el formato internacional, por ejemplo +573001234567.'];
    }
}
