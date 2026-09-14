@php($customProperties = \App\Support\PortalTheme::customProperties())
@if($customProperties)
    <style>:root{@foreach($customProperties as $property => $value){{ $property }}:{{ $value }};@endforeach}</style>
@endif
