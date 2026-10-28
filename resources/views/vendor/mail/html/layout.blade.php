@php
    $locale = app()->getLocale();
    $isRtl  = in_array($locale, ['fa', 'ar', 'he', 'ur', 'yi'], true);
    $appName = \App\Domains\Settings\Support\Branding::name() ?? config('app.name');
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
<title>{{ $appName }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
    .inner-body { width: 100% !important; }
    .footer { width: 100% !important; }
}
@media only screen and (max-width: 500px) {
    .button { width: 100% !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body style="box-sizing: border-box; font-family: 'Vazirmatn', Tahoma, 'Dubai', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; position: relative; -webkit-text-size-adjust: none; background-color: #ffffff; color: #52525b; height: 100%; line-height: 1.4; margin: 0; padding: 0; width: 100% !important;">

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation"
       style="box-sizing: border-box; font-family: 'Vazirmatn', Tahoma, 'Dubai', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #fafafa; margin: 0; padding: 0; width: 100%;">
<tr>
<td align="center" style="box-sizing: border-box;">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation"
       style="box-sizing: border-box; margin: 0; padding: 0; width: 100%;">

{{-- The brand mark sits outside the card, centered above it. --}}
{!! $header ?? '' !!}

<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0"
    style="box-sizing: border-box; background-color: #fafafa; border-bottom: 1px solid #fafafa; border-top: 1px solid #fafafa; margin: 0; padding: 0; width: 100%;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation"
       style="box-sizing: border-box; background-color: #ffffff; border-color: #e4e4e7; border-radius: 4px; border-width: 1px; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1); margin: 0 auto; padding: 0; width: 570px;">
<tr>
<td class="content-cell" style="box-sizing: border-box; max-width: 100vw; padding: 32px;">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}

</table>
</td>
</tr>
</table>
</body>
</html>
