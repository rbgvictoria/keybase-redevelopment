<div class="alert alert-{{$type}}">
    <div class="alert-header">
        {{ isset($title) ? $title : ucfirst($type) }}
    </div>
    <div class="alert-body">
        {{ $slot }}
    </div>
</div>