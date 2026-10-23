@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@foreach ((array) session('warnings', []) as $w)<div class="alert alert-warning">{{ $w }}</div>@endforeach
@if (session('activation_link'))
  <div class="alert alert-info" x-data="{c:false}">
    <strong>Link aktivasi (hanya ditampilkan sekali, berlaku 48 jam):</strong><br>
    <span class="mono" style="word-break:break-all">{{ session('activation_link') }}</span>
    <button type="button" class="btn btn-sm" style="margin-left:.5rem" @click="navigator.clipboard.writeText(@js(session('activation_link'))); c=true" x-text="c ? 'Tersalin' : 'Salin'">Salin</button>
  </div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">Periksa kembali isian berikut:
    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif
