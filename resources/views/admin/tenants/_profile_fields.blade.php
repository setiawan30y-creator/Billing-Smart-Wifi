<div class="card-body form-section">
  <p class="section-title">A. Profil perusahaan</p>
  <div class="form-grid">
    <x-field name="name" label="Nama perusahaan ISP" :value="$tenant->name" required placeholder="Nama perusahaan"/>
    @if ($creating)
      <x-field name="code" label="Kode ISP" :value="$suggestedCode" required hint="Huruf besar, angka, dan tanda minus. Unik dan tidak bisa diubah." style="text-transform:uppercase"/>
    @else
      <div class="field"><label>Kode ISP</label><input class="input mono" value="{{ $tenant->code }}" disabled></div>
    @endif
    <x-field name="pic_name" label="Nama penanggung jawab (PIC)" :value="$tenant->profile?->pic_name" required placeholder="Nama lengkap"/>
    <x-field name="whatsapp" label="Nomor WhatsApp" :value="$tenant->profile?->whatsapp" required placeholder="08xxxxxxxxxx" inputmode="tel"/>
    <x-field name="email" label="Email perusahaan" type="email" :value="$tenant->profile?->email" required placeholder="email@perusahaan.com"/>
    <x-field name="office_phone" label="Telepon kantor" :value="$tenant->profile?->office_phone" placeholder="Opsional"/>
    <x-field name="address" label="Alamat lengkap" type="textarea" :value="$tenant->profile?->address" required full rows="2" placeholder="Alamat kantor"/>
    <x-field name="province" label="Provinsi" :value="$tenant->profile?->province" required placeholder="Provinsi"/>
    <x-field name="city" label="Kota / kabupaten" :value="$tenant->profile?->city" required placeholder="Kota/kabupaten"/>
    <x-field name="country" label="Negara" :value="$tenant->profile?->country ?? 'Indonesia'"/>
    <x-field name="website" label="Website" type="url" :value="$tenant->profile?->website" placeholder="https://"/>
  </div>
</div>
<div class="card-body form-section">
  <p class="section-title">C. Domain & branding</p>
  <div class="form-grid">
    <x-field name="subdomain" label="Subdomain tenant" :value="$tenant->subdomain" placeholder="contoh: ispku" hint="{{ config('almara.platform_domain') ? 'Akan menjadi <subdomain>.'.config('almara.platform_domain') : 'Opsional. Atur ALMARA_PLATFORM_DOMAIN di .env untuk mengaktifkan.' }}"/>
    @if ($creating)
      <x-field name="custom_domain" label="Domain custom (opsional)" placeholder="billing.domainisp.com" hint="Status pending sampai DNS diverifikasi."/>
    @endif
    <x-field name="brand_name" label="Nama aplikasi / brand" :value="$tenant->brand_name" placeholder="Nama tampilan di aplikasi"/>
    <div class="field"><label>Tema default</label>
      <select name="theme" class="input">@foreach (['default' => 'Default', 'light' => 'Light', 'dark' => 'Dark'] as $k => $l)<option value="{{ $k }}" @selected(old('theme', $tenant->theme) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Zona waktu</label>
      <select name="timezone" class="input">@foreach (['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'] as $k => $l)<option value="{{ $k }}" @selected(old('timezone', $tenant->timezone ?? 'Asia/Jakarta') === $k)>{{ $l }}</option>@endforeach</select></div>
    <x-field name="logo" label="Logo (PNG/JPEG/WebP, maks 1 MB)" type="file" accept="image/png,image/jpeg,image/webp"/>
  </div>
</div>
