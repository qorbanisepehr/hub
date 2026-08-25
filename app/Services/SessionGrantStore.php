<?php

namespace App\Services;

/**
 * Remembers OTP access grants in the session so browser-native requests
 * (<img>, embed, pdf.js) that cannot send an X-Access-Token header can still
 * pass document serving authorization via their session cookie.
 *
 * Tokens are keyed by the owner's OtpService identifier; validity, expiry and
 * purpose coverage stay in the OTP cache (single source of truth) — the
 * session only records which token belongs to this browser.
 */
final class SessionGrantStore
{
    private const KEY = 'grant_tokens';

    /**
     * Bind a freshly issued grant token to this session.
     */
    public function remember(string $identifier, string $token): void
    {
        session()->put(self::KEY.'.'.$identifier, $token);
    }

    /**
     * The grant token previously bound to this session, if any.
     */
    public function token(string $identifier): ?string
    {
        $token = session(self::KEY.'.'.$identifier);

        return is_string($token) && $token !== '' ? $token : null;
    }
}
