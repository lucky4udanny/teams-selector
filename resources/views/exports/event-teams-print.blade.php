<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->name }} — Teams</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 1.5rem; color: #0f172a; }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p.meta { margin: 0 0 1.25rem; color: #64748b; font-size: 0.875rem; }
        table { border-collapse: collapse; width: 100%; font-size: 0.875rem; }
        th, td { border: 1px solid #cbd5e1; padding: 0.4rem 0.5rem; text-align: left; }
        th { background: #f1f5f9; }
        @media print {
            body { margin: 0.5rem; }
        }
    </style>
</head>
<body>
    <h1>{{ $event->name }}</h1>
    <p class="meta">
        {{ $event->event_date->format('M j, Y') }}
        @if($event->eventType)
            · {{ $event->eventType->name }}
        @endif
    </p>
    <table>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <script>window.onload = () => { window.print(); };</script>
</body>
</html>
