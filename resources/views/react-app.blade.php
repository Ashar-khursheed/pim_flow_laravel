<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    {{-- Inject SEO --}}
    @if($seoData)
        <title>{{ $seoData->meta_title }}</title>
        <meta name="description" content="{{ $seoData->meta_description }}">
        <link rel="canonical" href="{{ url()->current() }}">
        
        {{-- OpenGraph --}}
        <meta property="og:title" content="{{ $seoData->og_title }}">
        <meta property="og:description" content="{{ $seoData->og_description }}">
        @if(!empty($seoData->og_image_url))
            <meta property="og:image" content="{{ $seoData->og_image_url }}">
        @endif

        {{-- Schema --}}
        @if(!empty($seoData->schema))
            <script type="application/ld+json">
                {!! json_encode($seoData->schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}
            </script>
        @endif
    @endif
</head>
<body>
    {{-- React root, pass SEO data --}}
    <div id="root" data-seo='@json($seoData)'></div>
    
    {{-- React build JS --}}
    @viteReactRefresh
    @vite('resources/js/main.jsx') {{-- Or wherever your React entry is --}}
</body>
</html> -->
