<x-mail::message>
{{-- Email OTP delivery (OtpMail): reuses the published vendor/mail layout whose
     `dir` attribute switches to rtl for the fa locale. Copy keys live in
     lang/{fa,en}/auth.php so the mail speaks the same auth contract. --}}

# {{ __('auth.email_otp_greeting') }}

{{ __('auth.email_otp_body') }}

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

{{ __('auth.email_otp_expiry', ['minutes' => max(1, (int) ceil($expiresIn / 60))]) }}

{{ __('auth.email_otp_salutation') }}<br>
{{ config('app.name') }}
</x-mail::message>