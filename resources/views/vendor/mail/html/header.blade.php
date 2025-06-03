@props(['url'])

@php
	$finalUrl = $url ?? config('app.url');
	$logoUrl = config('app.logo_url');
@endphp

<tr>
	<td class="header">
		<a href="{{ $finalUrl }}" style="display: inline-block;">
			<img src="{{ $logoUrl }}" alt="Logo" style="max-height: 60px;">
		</a>
	</td>
</tr>
