@props(['status', 'label' => null])
<span class="badge badge-{{ status_badge($status) }}">{{ $label ?? $status }}</span>
