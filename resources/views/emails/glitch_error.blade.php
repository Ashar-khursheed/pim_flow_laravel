@component('mail::message')
# New Glitch Error Reported

**From:** {{ $email }}

**Contact:** {{ $contact }}

**Description:**
{{ $description }}

**Device:**
{{ $device }}

@if(!empty($images))
**Attached Images:**
@foreach($images as $image)
<img src="{{ $image }}" alt="Product" width="54" height="54" style="display: block; width: 200px; height: 200px; border: 1px solid #DFDFDF; border-radius: 4px; object-fit: cover;">
@endforeach
@endif

Thanks,<br>
Team HorecaStore
@endcomponent
