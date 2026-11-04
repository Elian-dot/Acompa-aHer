<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Solo acepta endpoints de los servicios push de los navegadores. El servidor hace
 * peticiones a esta dirección, así que una URL arbitraria sería un riesgo de SSRF.
 */
class PushEndpoint implements ValidationRule
{
    /** @var list<string> */
    private const ALLOWED_HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
    ];

    /** @var list<string> */
    private const ALLOWED_SUFFIXES = [
        '.push.services.mozilla.com',
        '.notify.windows.com',
        '.push.apple.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        $allowed = is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['user'], $parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443)
            && ($this->isAllowedHost($host));

        if (! $allowed) {
            $fail('El servicio de notificaciones de este navegador no es compatible.');
        }
    }

    private function isAllowedHost(string $host): bool
    {
        if (in_array($host, self::ALLOWED_HOSTS, true)) {
            return true;
        }

        foreach (self::ALLOWED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
