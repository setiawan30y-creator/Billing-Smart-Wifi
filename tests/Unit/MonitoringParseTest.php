<?php

namespace Tests\Unit;

use App\Integrations\Monitoring\IcmpProbe;
use App\Integrations\Monitoring\RouterOsClient;
use App\Integrations\Monitoring\RouterOsProbe;
use PHPUnit\Framework\TestCase;

class MonitoringParseTest extends TestCase
{
    public function test_ping_output_linux_windows_indonesian(): void
    {
        $linux = "4 packets transmitted, 4 received, 0% packet loss, time 3004ms\nrtt min/avg/max/mdev = 9.1/12.48/15.2/2.1 ms";
        $r = IcmpProbe::parse($linux);
        $this->assertSame('up', $r->status);
        $this->assertSame(12.48, $r->latencyMs);
        $this->assertSame(0.0, $r->lossPct);

        $win = "Packets: Sent = 4, Received = 3, Lost = 1 (25% loss),\nApproximate round trip times in milli-seconds:\n    Minimum = 10ms, Maximum = 20ms, Average = 14ms";
        $r = IcmpProbe::parse($win);
        $this->assertSame(14.0, $r->latencyMs);
        $this->assertSame(25.0, $r->lossPct);

        $winId = "Paket: Terkirim = 4, Diterima = 0, Hilang = 4 (100% hilang),";
        $this->assertSame('down', IcmpProbe::parse($winId)->status);

        $this->assertSame('error', IcmpProbe::parse('ping: unknown host')->status);
    }

    public function test_routeros_length_encoding_and_uptime(): void
    {
        $this->assertSame("\x05", RouterOsClient::encodeLength(5));
        $this->assertSame("\x80\x80", RouterOsClient::encodeLength(0x80));
        $this->assertSame("\xC0\x40\x00", RouterOsClient::encodeLength(0x4000));
        $this->assertSame(1 * 604800 + 2 * 86400 + 3 * 3600 + 4 * 60 + 5, RouterOsProbe::parseUptime('1w2d03:04:05'));
        $this->assertSame(3 * 86400 + 4 * 3600 + 5 * 60 + 6, RouterOsProbe::parseUptime('3d4h5m6s'));
    }
}
