@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'seas-input']) }}>
