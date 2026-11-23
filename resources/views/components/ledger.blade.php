@props(['headers', 'rows', 'label'])

@if (count($rows) === 0)
    <p class="empty-state">{{ $slot->isEmpty() ? 'Belum ada data untuk ditampilkan.' : $slot }}</p>
@else
    <div class="table-wrap">
        <table class="ledger" role="table" aria-label="{{ $label }}">
            <thead role="rowgroup"><tr role="row">
                @foreach ($headers as $heading)<th role="columnheader" scope="col">{{ $heading }}</th>@endforeach
            </tr></thead>
            <tbody role="rowgroup">
                @foreach ($rows as $row)
                    <tr role="row">
                        @foreach (array_values($row) as $index => $cell)
                            <td role="cell" @class(['numeric' => is_array($cell) && !empty($cell['number'])])>
                                <span class="cell-label" aria-hidden="true">{{ $headers[$index] }}</span>
                                @if (is_array($cell) && isset($cell['url']))
                                    <span><a class="table-action" href="{{ $cell['url'] }}">{{ $cell['label'] }}</a></span>
                                @else
                                    <span>{{ is_array($cell) ? $cell['label'] : ($cell ?? '–') }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
