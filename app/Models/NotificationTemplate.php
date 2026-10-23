<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use BelongsToTenant;

    public const EVENTS = [
        'invoice_created' => 'Invoice dibuat',
        'reminder_before_due' => 'Pengingat sebelum jatuh tempo',
        'due_today' => 'Tagihan jatuh tempo',
        'overdue' => 'Tagihan overdue',
        'payment_received' => 'Pembayaran diterima & kuitansi',
        'service_disruption' => 'Gangguan layanan',
        'service_activated' => 'Aktivasi layanan',
        'service_isolated' => 'Penangguhan layanan',
        'service_restored' => 'Pemulihan layanan',
        'ticket_updated' => 'Pembaruan tiket bantuan',
        'mass_announcement' => 'Pengumuman gangguan massal',
    ];

    public const PLACEHOLDERS = [
        'nama_pelanggan' => 'Nama pelanggan',
        'id_pelanggan' => 'ID pelanggan',
        'nama_isp' => 'Nama ISP',
        'nomor_invoice' => 'Nomor invoice',
        'periode' => 'Periode tagihan',
        'total' => 'Total tagihan',
        'sisa_tagihan' => 'Sisa tagihan',
        'jatuh_tempo' => 'Tanggal jatuh tempo',
        'jumlah_bayar' => 'Jumlah pembayaran',
        'paket' => 'Nama paket',
        'kontak_isp' => 'Kontak ISP',
        'nomor_tiket' => 'Nomor tiket',
        'status_tiket' => 'Status tiket',
        'isi_pengumuman' => 'Isi pengumuman',
        'link_portal' => 'Link portal pelanggan',
    ];

    public const DEFAULTS = [
        'invoice_created' => "Yth. {nama_pelanggan},\nTagihan internet {periode} nomor {nomor_invoice} sebesar {total} telah terbit. Jatuh tempo {jatuh_tempo}.\nTerima kasih.\n- {nama_isp}",
        'reminder_before_due' => "Yth. {nama_pelanggan},\nPengingat: tagihan {nomor_invoice} sebesar {sisa_tagihan} jatuh tempo pada {jatuh_tempo}.\n- {nama_isp}",
        'due_today' => "Yth. {nama_pelanggan},\nHari ini adalah jatuh tempo tagihan {nomor_invoice} sebesar {sisa_tagihan}.\n- {nama_isp}",
        'overdue' => "Yth. {nama_pelanggan},\nTagihan {nomor_invoice} sebesar {sisa_tagihan} telah melewati jatuh tempo ({jatuh_tempo}). Mohon segera melakukan pembayaran.\nInfo: {kontak_isp}\n- {nama_isp}",
        'payment_received' => "Yth. {nama_pelanggan},\nPembayaran sebesar {jumlah_bayar} untuk {nomor_invoice} telah kami terima. Terima kasih.\n- {nama_isp}",
        'service_disruption' => "Yth. {nama_pelanggan},\nSaat ini terjadi gangguan layanan di area Anda. Tim kami sedang menangani. Mohon maaf atas ketidaknyamanannya.\n- {nama_isp}",
        'service_activated' => "Yth. {nama_pelanggan},\nLayanan internet paket {paket} Anda telah aktif. Selamat menikmati.\n- {nama_isp}",
        'service_isolated' => "Yth. {nama_pelanggan},\nLayanan internet Anda sementara ditangguhkan. Silakan hubungi {kontak_isp}.\n- {nama_isp}",
        'service_restored' => "Yth. {nama_pelanggan},\nLayanan internet Anda telah dipulihkan. Terima kasih.\n- {nama_isp}",
        'ticket_updated' => "Yth. {nama_pelanggan},\nTiket {nomor_tiket} Anda diperbarui. Status: {status_tiket}.\nLihat detail: {link_portal}\n- {nama_isp}",
        'mass_announcement' => "Yth. {nama_pelanggan},\n{isi_pengumuman}\n- {nama_isp}",
    ];

    protected $fillable = ['event', 'channel', 'body', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /** @return string[] placeholder tidak dikenal */
    public static function unknownPlaceholders(string $body): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $body, $m);

        return array_values(array_diff(array_unique($m[1]), array_keys(self::PLACEHOLDERS)));
    }

    public static function render(string $body, array $data): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => (string) ($data[$m[1]] ?? $m[0]), $body);
    }
}
