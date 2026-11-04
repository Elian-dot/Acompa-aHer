<?php

namespace App\Http\Requests;

use App\Rules\PushEndpoint;
use Illuminate\Foundation\Http\FormRequest;

class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2048', new PushEndpoint],
            'keys.p256dh' => $this->isMethod('DELETE') ? ['nullable'] : ['required', 'string', 'max:255'],
            'keys.auth' => $this->isMethod('DELETE') ? ['nullable'] : ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
            'expirationTime' => ['nullable', 'numeric'],
        ];
    }
}
