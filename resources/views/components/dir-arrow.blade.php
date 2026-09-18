{{--
    Direction-aware text arrow. "forward" points in the reading direction (→ in LTR, ← in RTL),
    "back" points against it. The glyph is mirrored with a CSS transform under [dir="rtl"], so
    the same markup is correct for every locale.
--}}
@props(['back' => false])
<span {{ $attributes->merge(['class' => 'inline-block rtl:-scale-x-100']) }} aria-hidden="true">{!! $back ? '&larr;' : '&rarr;' !!}</span>
