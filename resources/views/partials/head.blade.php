<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>{{ isset($title) ? $title.' — '.config('app.name') : config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

@vite(['resources/css/app.css', 'resources/js/app.js'])

@livewireStyles
