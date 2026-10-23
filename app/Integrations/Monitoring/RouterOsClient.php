<?php

namespace App\Integrations\Monitoring;

use RuntimeException;

/**
 * Klien minimal MikroTik RouterOS API (port 8728) — hanya untuk perintah baca (print/monitor).
 * Disarankan memakai user RouterOS dengan group "read".
 */
class RouterOsClient
{
    /** @var resource|null */
    protected $socket = null;

    public function connect(string $host, int $port, string $user, string $password, float $timeout = 4.0): void
    {
        $this->socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        if (! $this->socket) {
            throw new RuntimeException("Tidak dapat terhubung ke RouterOS API {$host}:{$port} ({$errstr}).");
        }
        stream_set_timeout($this->socket, (int) ceil($timeout));
        $res = $this->command('/login', ['name' => $user, 'password' => $password]);
        if (($res['done'] ?? false) !== true) {
            throw new RuntimeException('Login RouterOS gagal: '.($res['trap'] ?? 'kredensial ditolak'));
        }
    }

    /** @return array{done: bool, rows: array<int, array<string,string>>, trap: ?string} */
    public function command(string $cmd, array $args = []): array
    {
        $words = [$cmd];
        foreach ($args as $k => $v) {
            $words[] = str_starts_with((string) $k, '?') ? $k.'='.$v : '='.$k.'='.$v;
        }
        $this->writeSentence($words);

        $rows = [];
        $trap = null;
        while (true) {
            $sentence = $this->readSentence();
            $type = array_shift($sentence);
            $attrs = [];
            foreach ($sentence as $w) {
                if (preg_match('/^=([^=]+)=(.*)$/s', $w, $m)) {
                    $attrs[$m[1]] = $m[2];
                }
            }
            if ($type === '!re') {
                $rows[] = $attrs;
            } elseif ($type === '!trap') {
                $trap = $attrs['message'] ?? 'error';
            } elseif ($type === '!fatal') {
                throw new RuntimeException('RouterOS fatal: '.implode(' ', $sentence));
            } elseif ($type === '!done') {
                return ['done' => $trap === null, 'rows' => $rows, 'trap' => $trap, 'ret' => $attrs['ret'] ?? null];
            }
        }
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public static function encodeLength(int $len): string
    {
        return match (true) {
            $len < 0x80 => chr($len),
            $len < 0x4000 => pack('n', $len | 0x8000),
            $len < 0x200000 => substr(pack('N', $len | 0xC00000), 1),
            $len < 0x10000000 => pack('N', $len | 0xE0000000),
            default => chr(0xF0).pack('N', $len),
        };
    }

    protected function writeSentence(array $words): void
    {
        $buf = '';
        foreach ($words as $w) {
            $buf .= self::encodeLength(strlen($w)).$w;
        }
        $buf .= "\0";
        if (@fwrite($this->socket, $buf) === false) {
            throw new RuntimeException('Gagal mengirim perintah ke RouterOS.');
        }
    }

    protected function readSentence(): array
    {
        $words = [];
        while (($len = $this->readLength()) > 0) {
            $words[] = $this->readBytes($len);
        }

        return $words;
    }

    protected function readLength(): int
    {
        $b = ord($this->readBytes(1));
        if (($b & 0x80) === 0) {
            return $b;
        }
        if (($b & 0xC0) === 0x80) {
            return (($b & 0x3F) << 8) | ord($this->readBytes(1));
        }
        if (($b & 0xE0) === 0xC0) {
            $r = $this->readBytes(2);

            return (($b & 0x1F) << 16) | (ord($r[0]) << 8) | ord($r[1]);
        }
        if (($b & 0xF0) === 0xE0) {
            $r = $this->readBytes(3);

            return (($b & 0x0F) << 24) | (ord($r[0]) << 16) | (ord($r[1]) << 8) | ord($r[2]);
        }

        return unpack('N', $this->readBytes(4))[1];
    }

    protected function readBytes(int $n): string
    {
        $data = '';
        while (strlen($data) < $n) {
            $chunk = fread($this->socket, $n - strlen($data));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                throw new RuntimeException($meta['timed_out'] ? 'Waktu tunggu RouterOS habis.' : 'Koneksi RouterOS terputus.');
            }
            $data .= $chunk;
        }

        return $data;
    }
}
