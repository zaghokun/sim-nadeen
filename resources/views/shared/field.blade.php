@php
    $type = $field['type'] ?? 'text';
    $value = $type === 'password' ? '' : old($name, $values[$name] ?? null);
    $value = is_scalar($value) ? $value : '';
    $required = in_array('required', $field['rules'] ?? [], true);
    $descriptions = array_filter([
        isset($field['hint']) ? $name.'-hint' : null,
        $errors->has($name) ? $name.'-error' : null,
    ]);
    $accessibility = new \Illuminate\View\ComponentAttributeBag([
        'aria-describedby' => $descriptions ? implode(' ', $descriptions) : null,
        'aria-invalid' => $errors->has($name) ? 'true' : null,
    ]);
@endphp
<div @class(['field', 'field-wide' => in_array($type, ['textarea', 'checkbox'], true)])>
    @if ($type === 'checkbox')
        <input type="hidden" name="{{ $name }}" value="0">
        <label class="checkbox-label" for="{{ $name }}">
            <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" @checked((bool) $value) {{ $accessibility }}>
            {{ $field['label'] }}
        </label>
    @else
        <label for="{{ $name }}">{{ $field['label'] }}</label>
        @switch($type)
            @case('textarea')
                <textarea id="{{ $name }}" name="{{ $name }}" rows="3" @required($required) {{ $accessibility }}>{{ $value }}</textarea>
                @break
            @case('select')
                <select id="{{ $name }}" name="{{ $name }}" @required($required) {{ $accessibility }}>
                    <option value="">Pilih {{ \Illuminate\Support\Str::lower($field['label']) }}</option>
                    @foreach ($field['options'] ?? [] as $key => $label)
                        <option value="{{ $key }}" @selected((string) $key === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @break
            @default
                <input
                    id="{{ $name }}"
                    name="{{ $name }}"
                    type="{{ in_array($type, ['integer', 'decimal'], true) ? 'text' : $type }}"
                    @if ($type !== 'password') value="{{ $value }}" @endif
                    @if ($type === 'integer') inputmode="numeric" @endif
                    @if ($type === 'decimal') inputmode="decimal" @endif
                    @if (isset($field['autocomplete'])) autocomplete="{{ $field['autocomplete'] }}" @endif
                    @if (isset($field['max'])) maxlength="{{ $field['max'] }}" @endif
                    @if ($field['autofocus'] ?? false) autofocus @endif
                    @required($required)
                    @readonly($field['readonly'] ?? false)
                    {{ $accessibility }}
                >
        @endswitch
    @endif

    @if (isset($field['hint']))
        <small class="field-hint" id="{{ $name }}-hint">{{ $field['hint'] }}</small>
    @endif
    @error($name)
        <p class="field-error" id="{{ $name }}-error" role="alert">{{ $message }}</p>
    @enderror
</div>
