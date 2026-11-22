@switch($key)
    @case('harga_standar')
    @case('total_hpp')
        @php([$whole, $cents] = explode('.', $value, 2))
        Rp {{ preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole) }}{{ $cents !== '00' ? ','.$cents : '' }}
        @break
    @case('markup')
        {{ str_replace('.', ',', rtrim(rtrim($value, '0'), '.')) }}%
        @break
    @case('berlaku_mulai')
        {{ $value->format('d/m/Y') }}
        @break
    @case('arah')
        {{ \App\Models\CashCategory::DIRECTIONS[$value] }}
        @break
    @case('kelompok')
        {{ \App\Models\CashCategory::GROUPS[$value] }}
        @break
    @case('nonrutin')
        {{ $value ? 'Nonrutin' : 'Rutin' }}
        @break
    @default
        {{ $value ?? '–' }}
@endswitch
