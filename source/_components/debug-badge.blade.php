<span class="badge 
    {{ isset($variant) && $variant == 'outline' ? 'badge-outline' : 'badge-fill' }} 
    badge-{{ $type }}" aria-hidden="true" aria-label="debug level: {{ $type }}">{{ ucfirst($type) }}</span>