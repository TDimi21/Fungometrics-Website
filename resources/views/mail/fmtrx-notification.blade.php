<x-mail::message>
# {{ $heading }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@if (!empty($actionUrl) && !empty($actionText))
<x-mail::button :url="$actionUrl">{{ $actionText }}</x-mail::button>

<x-slot:subcopy>
If the button does not work, copy this link into your browser: [{{ $actionUrl }}]({{ $actionUrl }})
</x-slot:subcopy>
@endif

The FMTRX Team
</x-mail::message>
