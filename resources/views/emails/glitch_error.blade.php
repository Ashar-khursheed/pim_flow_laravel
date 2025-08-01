@component('mail::message')
# New Glitch Error Reported

**From:** {{ $email }}

**Description:**
{{ $description }}

@if(!empty($images))
**Attached Images:**
@foreach($images as $image)
- [{{ $image }}]({{ $image }})
@endforeach
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
