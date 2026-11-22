@props(['active'])

<span @class(['record-status', 'record-active' => $active])>{{ $active ? 'Aktif' : 'Nonaktif' }}</span>
