@props(['points' => [], 'unit' => '', 'height' => 160, 'color' => 'var(--primary)'])
{{-- Grafik garis sederhana (SVG). Titik null = tidak terukur → garis terputus, tidak diinterpolasi. --}}
@php
  $vals = array_filter(array_column($points, 1), fn ($v) => $v !== null);
  $w = 800; $h = $height; $pad = 28;
  $max = $vals ? max($vals) : 0; $max = $max > 0 ? $max * 1.15 : 1;
  $n = count($points);
  $segments = []; $cur = [];
  foreach ($points as $i => [$label, $v]) {
      if ($v === null) { if ($cur) { $segments[] = $cur; $cur = []; } continue; }
      $x = $n > 1 ? $pad + ($w - 2 * $pad) * $i / ($n - 1) : $w / 2;
      $y = $h - $pad - ($h - 2 * $pad) * ($v / $max);
      $cur[] = round($x, 1).','.round($y, 1);
  }
  if ($cur) $segments[] = $cur;
@endphp
@if (! $vals)
  <div class="empty" style="padding:1.5rem">Belum ada data terukur pada rentang ini.</div>
@else
<svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%;height:auto;display:block" role="img" aria-label="grafik">
  @foreach ([0, .5, 1] as $g)
    @php $gy = $h - $pad - ($h - 2 * $pad) * $g; @endphp
    <line x1="{{ $pad }}" x2="{{ $w - $pad }}" y1="{{ $gy }}" y2="{{ $gy }}" stroke="var(--border)" stroke-width="1"/>
    <text x="2" y="{{ $gy + 4 }}" font-size="11" fill="var(--text-3)">{{ round($max / 1.15 * $g * 1.15, 1) }}</text>
  @endforeach
  @foreach ($segments as $seg)
    @if (count($seg) === 1)<circle cx="{{ explode(',', $seg[0])[0] }}" cy="{{ explode(',', $seg[0])[1] }}" r="2.5" fill="{{ $color }}"/>@else
    <polyline points="{{ implode(' ', $seg) }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>@endif
  @endforeach
  <text x="{{ $pad }}" y="{{ $h - 6 }}" font-size="11" fill="var(--text-3)">{{ $points[0][0] ?? '' }}</text>
  <text x="{{ $w - $pad }}" y="{{ $h - 6 }}" font-size="11" fill="var(--text-3)" text-anchor="end">{{ $points[$n - 1][0] ?? '' }}</text>
  <text x="{{ $w - $pad }}" y="14" font-size="11" fill="var(--text-3)" text-anchor="end">{{ $unit }}</text>
</svg>
@endif
