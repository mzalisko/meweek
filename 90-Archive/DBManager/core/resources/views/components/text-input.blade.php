@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-acc focus:ring-acc rounded-md shadow-sm']) }}>

