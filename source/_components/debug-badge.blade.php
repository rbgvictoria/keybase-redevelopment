@if (isset($variant) && $variant == 'outline')
    @if ($type == 'error')
        <span class="inline-block 
            py-0.5 px-2
            border border-red-500 rounded-full
            text-red-500 font-bold">Error</span>
    @elseif ($type == 'warning')
        <span class="inline-block 
            py-0.5 px-2
            border border-yellow-600 rounded-full
            text-yellow-600 font-bold">Warning</span>
    @elseif ($type == 'info')
        <span class="inline-block 
            py-0.5 px-2
            border border-blue-600 rounded-full
            text-blue-600
            font-bold">Info</span>
    @endif
@else
    @if ($type == 'error')
        <span class="inline-block 
            py-0.5 px-2
            border border-red-500 rounded-full
            bg-red-500
            text-white font-bold">Error</span>
    @elseif ($type == 'warning')
        <span class="inline-block 
            py-0.5 px-2
            border border-yellow-600 rounded-full
            bg-yellow-500
            text-white font-bold">Warning</span>
    @elseif ($type == 'info')
        <span class="inline-block 
            py-0.5 px-2
            border border-blue-600 rounded-full
            bg-blue-600
            text-white font-bold">Info</span>
    @endif
@endif