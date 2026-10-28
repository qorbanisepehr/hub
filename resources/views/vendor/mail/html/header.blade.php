{{-- Mail header: brand mark + wordmark, or a plain app-name link when the
     brand has no image. An inlined SVG is preferred because inlining is the
     only way to recolor the mark to the brand color; a raster upload is the
     fallback (used as-is) and the app-name link is the final fallback so the
     header is never empty. Known limitation: Gmail and Outlook drop inline
     <svg>, so with an SVG brand they show no mark — upload a PNG if the mark
     must appear there. --}}
@props(['url'])

@php
    $locale = app()->getLocale();
    $isRtl  = in_array($locale, ['fa', 'ar', 'he', 'ur', 'yi'], true);

    $appName = \App\Domains\Settings\Support\Branding::name() ?? config('app.name');

    $logotypeSvg = \App\Domains\Settings\Support\Branding::logotypeSvg();
    $logotypeUrl = \App\Domains\Settings\Support\Branding::logotypeUrl();
    $logoSvg     = \App\Domains\Settings\Support\Branding::logoSvg();
    $logoUrl     = \App\Domains\Settings\Support\Branding::logoUrl();

    $brandColor     = \App\Domains\Settings\Support\Branding::primaryColor()   ?? '#18181b';
    $secondaryColor = \App\Domains\Settings\Support\Branding::secondaryColor() ?? '#18181b';

    $hasLogo     = $logoSvg || $logoUrl;
    $hasLogotype = $logotypeSvg || $logotypeUrl;

    $prepareSvg = function (?string $svg, int $width, int $height, ?string $color = null): ?string {
        if (empty($svg)) return null;
        if ($color) {
            $svg = preg_replace(
                '/\sfill="(?:black|#000|#000000|currentColor|#18181b)"/i',
                ' fill="' . $color . '"',
                $svg
            );
        }
        return preg_replace_callback(
            '/<svg\b([^>]*)>/i',
            function (array $m) use ($width, $height): string {
                $attrs = preg_replace('/\s(?:width|height)="[^"]*"/i', '', $m[1]);
                return '<svg' . $attrs
                    . ' width="' . $width . '" height="' . $height . '"'
                    . ' style="display:block;max-width:100%;height:auto;">';
            },
            $svg,
            1
        );
    };
@endphp

<tr>
<td class="header" style="box-sizing: border-box; padding: 25px 0; text-align: center;">
@if ($hasLogo || $hasLogotype)
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"
       style="box-sizing: border-box; margin: 0 auto; max-width: 100%;">
<tr>

@if ($hasLogo)
    <td style="box-sizing: border-box; vertical-align: middle; line-height: 0;
               padding-{{ $isRtl ? 'left' : 'right' }}: 10px;">
    <a href="{{ $url }}"
       style="display:inline-block;text-decoration:none;border:0;line-height:0;">
    @if ($logoSvg)
        {!! $prepareSvg($logoSvg, 40, 40, $brandColor) !!}
    @elseif ($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ $appName }}"
             width="40" height="40"
             style="display:block;border:0;outline:none;text-decoration:none;height:40px;width:auto;max-width:100%;">
    @endif
    </a>
    </td>
@endif

@if ($hasLogotype)
    <td style="box-sizing: border-box; vertical-align: middle; line-height: 0;">
    <a href="{{ $url }}"
       style="display:inline-block;text-decoration:none;border:0;line-height:0;">
    @if ($logotypeSvg)
        {!! $prepareSvg($logotypeSvg, 120, 28, $secondaryColor) !!}
    @elseif ($logotypeUrl)
        <img src="{{ $logotypeUrl }}" alt="{{ $appName }}"
             height="28"
             style="display:block;border:0;outline:none;text-decoration:none;height:28px;width:auto;max-width:100%;">
    @endif
    </a>
    </td>
@endif

</tr>
</table>
@else
<a href="{{ $url }}"
   style="color: #18181b; font-size: 19px; font-weight: bold; text-decoration: none;">{{ $appName }}</a>
@endif
</td>
</tr>
