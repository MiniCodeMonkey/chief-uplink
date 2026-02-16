@if(count($events) === 1)
{{ $events[0]['title'] }}
@else
{{ count($events) }} notifications from Chief
@endif

@foreach($events as $event)
@if(count($events) > 1)
{{ $event['title'] }}
@endif
{{ $event['body'] }}
@if(!empty($event['server']))
Server: {{ $event['server'] }}
@endif
@if(!empty($event['url']))
View: {{ config('app.url') }}{{ $event['url'] }}
@endif

@endforeach
---
Unsubscribe: {{ $unsubscribeUrl }}
Chief — chiefloop.com
