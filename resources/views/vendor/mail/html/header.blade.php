@props(['url'])

@php
	$finalUrl = $url ?? config('app.url');

	$backendURL = config('app.backend_url');
	$logoUrl = $backendURL . (config('app.website') == 'UAE' ? '/uae_logo.png' : '/us_logo.png');
@endphp

<tr>
	<td class="header">
		<a href="{{ $finalUrl }}" style="display: inline-block;">
			<img src="{{ $logoUrl }}" alt="Logo" style="max-height: 60px;">
		</a>
	</td>
</tr>
