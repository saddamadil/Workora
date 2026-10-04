{{ $heading }}

{{ trim($body) }}
@if (! empty($facts))

@foreach ($facts as $k => $v)
{{ $k }}: {{ $v }}
@endforeach
@endif
@if (! empty($url))

{{ $button ?? 'Open' }}: {{ $url }}
@endif

--
{{ $footer ?? 'Sent by '.config('app.name', 'Freelancy').'.' }}
