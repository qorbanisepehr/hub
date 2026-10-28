<x-mail::message>
{{-- questionnaire.submitted mail representation (architecture 26/32): reuses
     the canonical title/body contract; RTL layout comes from the published
     vendor/mail components. --}}

# {{ __('notifications.questionnaire_submitted.title') }}

{{ __('notifications.questionnaire_submitted.body') }}

<x-mail::button :url="$reviewUrl">
{{ __('notifications.questionnaire_submitted.email_action') }}
</x-mail::button>

{{ __('notifications.questionnaire_submitted.email_salutation') }}<br>
{{ config('app.name') }}
</x-mail::message>