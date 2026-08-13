@props(['name'])

@error($name)
    <p {{ $attributes->class('text-sm text-red-600') }}>{{ $message }}</p>
@enderror
