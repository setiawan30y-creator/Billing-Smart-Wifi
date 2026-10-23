@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null, 'full' => false])
<div class="field {{ $full ? 'full' : '' }}">
  <label for="f-{{ $name }}" class="{{ $required ? 'req' : '' }}">{{ $label }}</label>
  @if ($type === 'textarea')
    <textarea id="f-{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['class' => 'input'.($errors->has($name) ? ' is-invalid' : '')]) }} @required($required)>{{ old($name, $value) }}</textarea>
  @else
    <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' || $type === 'file' ? '' : old($name, $value) }}" {{ $attributes->merge(['class' => 'input'.($errors->has($name) ? ' is-invalid' : '')]) }} @required($required)>
  @endif
  @if ($hint)<div class="hint">{{ $hint }}</div>@endif
  @error($name)<div class="error">{{ $message }}</div>@enderror
</div>
