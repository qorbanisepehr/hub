@php
    $minutes = max(1, (int) ceil($expiresIn / 60));
    $issuedAt = $delivery?->issuedAt;

    // Only the context the flow actually knows about; a flow without request
    // context (console/queue) renders no card at all.
    $details = array_values(array_filter([
        $delivery?->ipAddress ? [
            'label' => __('auth.email_otp_ip_label'),
            'value' => $delivery->ipAddress,
            'ltr' => true,
        ] : null,
        $issuedAt ? [
            'label' => __('auth.email_otp_time_label'),
            'value' => \App\Support\PersianDate::format($issuedAt),
            'ltr' => false,
        ] : null,
    ]));
@endphp

<x-mail::message>
# {{ __('auth.email_otp_title') }}

{{ __('auth.email_otp_greeting') }} 👋

{{ __('auth.email_otp_intro') }}

@if ($details !== [])
<x-mail::panel>
<table class="otp-detail" role="presentation" cellpadding="0" cellspacing="0" border="0">
@foreach ($details as $detail)
<tr class="otp-detail-row{{ $loop->last ? '' : ' otp-detail-separator' }}">
<td class="otp-detail-label">{{ $detail['label'] }}</td>
{{-- The cell keeps the document direction so both values align to the same
     edge; only the value itself is isolated as LTR. --}}
<td class="otp-detail-value"><span @if ($detail['ltr']) dir="ltr"@endif>{{ $detail['value'] }}</span></td>
</tr>
@endforeach
</table>
</x-mail::panel>
@endif

{{ __('auth.email_otp_body') }}

<p class="otp-code" dir="ltr">{{ $code }}</p>

<p class="otp-expiry">{{ __('auth.email_otp_expiry', ['minutes' => $minutes]) }}</p>

<table class="otp-warning" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td>⚠ {{ __('auth.email_otp_warning') }}</td>
</tr>
</table>

{{-- Why this mail exists, rather than a letter sign-off: a transactional
     verification mail is not correspondence. --}}
<x-slot:subcopy>
{{ __('auth.email_otp_reason') }}
</x-slot:subcopy>
</x-mail::message>
