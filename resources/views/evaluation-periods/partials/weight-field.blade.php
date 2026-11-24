@php
    $fieldName = $section . '[' . $key . ']';
    $fieldError = $errors->first($section . '.' . $key);
    $errorId = 'weight-' . $section . '-' . $key . '-error';
@endphp
<div class="space-y-1.5">
    <label for="{{ $fieldName }}" class="block break-words text-sm font-medium text-slate-800">{{ $label }} (%)</label>
    <x-form.input :name="$fieldName" numeric="decimal" min="0" max="100"
        :required="$required ?? false" data-weight="{{ $totalGroup }}"
        :value="old($section . '.' . $key, $value)"
        :has-error="(bool) $fieldError"
        :aria-invalid="$fieldError ? 'true' : null"
        :aria-describedby="'weight-' . $totalGroup . '-total' . ($fieldError ? ' ' . $errorId : '')" />
    @if ($fieldError)
        <p id="{{ $errorId }}" class="text-xs text-acode-danger-dark">{{ $fieldError }}</p>
    @endif
</div>
