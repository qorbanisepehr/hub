{{--
    Shared print layout for every PDF document the kernel renders (profile
    records and tabular lists alike). CSS stays inside the mPDF-supported
    subset (CSS 2.1 tables): repeating table headers, no row splitting, and
    a fixed RTL direction — the renderer writes HTML, mPDF shapes Persian.
--}}
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc->title }}</title>
    <style>
        body { font-family: {{ config('exports.documents.font') }}; font-size: 10.5pt; color: #1c2538; line-height: 1.7; }
        h1 { font-size: 15pt; margin: 0 0 4pt; }
        .subtitle { color: #666; font-size: 10pt; margin-bottom: 10pt; }
        .meta { margin: 0 0 14pt; color: #444; font-size: 9pt; }
        .meta span { margin-inline-end: 14pt; }
        h2 { font-size: 12pt; margin: 14pt 0 6pt; padding-bottom: 3pt; border-bottom: 1px solid #c5ccd8; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #9aa4b5; padding: 4pt 6pt; text-align: right; vertical-align: top; }
        th { background-color: #eef1f6; font-weight: bold; }
        .kv th { width: 30%; }
        .caption { font-weight: bold; margin: 8pt 0 4pt; }
        .empty { color: #888; }
    </style>
</head>
<body>
    @if($doc->title !== '')
        <h1>{{ $doc->title }}</h1>
    @endif
    @if($doc->subtitle !== '')
        <div class="subtitle">{{ $doc->subtitle }}</div>
    @endif
    @if($doc->meta !== [])
        <div class="meta">
            @foreach($doc->meta as $line)
                <span>{{ $line->label }}: {{ $line->value }}</span>
            @endforeach
        </div>
    @endif

    @foreach($doc->sections as $section)
        @if($section->heading !== '')
            <h2>{{ $section->heading }}</h2>
        @endif

        @if($section->fields !== [])
            <table class="kv">
                @foreach($section->fields as $field)
                    <tr>
                        <th>{{ $field->label }}</th>
                        <td>{{ $field->value }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @foreach($section->tables as $table)
            @if($table->caption !== '')
                <div class="caption">{{ $table->caption }}</div>
            @endif

            @if($table->isEmpty())
                <div class="empty">—</div>
            @else
                <table>
                    <thead>
                        <tr>
                            @foreach($table->headers as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($table->rows as $row)
                            <tr>
                                @foreach($table->headers as $index => $header)
                                    <td>{{ $row[$index] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endforeach
</body>
</html>
