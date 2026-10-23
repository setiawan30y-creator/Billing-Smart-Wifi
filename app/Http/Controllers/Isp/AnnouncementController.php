<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\NetworkDevice;
use App\Models\NetworkIncident;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\ServiceAnnouncement;
use App\Models\ServicePackage;
use App\Services\AnnouncementService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('isp.announcements.index', ['announcements' => ServiceAnnouncement::with('incident')->latest()->paginate(20)]);
    }

    public function create(Request $request)
    {
        $incident = $request->integer('incident') ? NetworkIncident::find($request->integer('incident')) : null;

        return view('isp.announcements.create', [
            'incident' => $incident,
            'incidents' => NetworkIncident::where('status', '!=', 'resolved')->latest('started_at')->get(),
            'odps' => Odp::orderBy('code')->get(['id', 'code', 'name']),
            'odcs' => Odc::orderBy('code')->get(['id', 'code', 'name']),
            'devices' => NetworkDevice::orderBy('name')->get(['id', 'name']),
            'packages' => ServicePackage::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenant()->id;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1500'],
            'target_mode' => ['required', Rule::in(array_keys(ServiceAnnouncement::MODES))],
            'network_incident_id' => ['nullable', 'required_if:target_mode,incident', Rule::exists('network_incidents', 'id')->where('tenant_id', $tenantId)],
            'targets' => ['nullable', 'array'],
            'targets.*' => ['integer'],
            'visible_until' => ['nullable', 'date', 'after:now'],
        ], ['network_incident_id.required_if' => 'Pilih insiden.']);
        $mode = $data['target_mode'];
        $targets = array_map('intval', $data['targets'] ?? []);
        if (in_array($mode, ['odp', 'odc', 'device', 'package'], true)) {
            $valid = match ($mode) {
                'odp' => Odp::whereIn('id', $targets)->pluck('id'), 'odc' => Odc::whereIn('id', $targets)->pluck('id'),
                'device' => NetworkDevice::whereIn('id', $targets)->pluck('id'), 'package' => ServicePackage::whereIn('id', $targets)->pluck('id'),
            };
            if ($valid->isEmpty()) {
                return back()->withInput()->withErrors(['targets' => 'Pilih minimal satu target.']);
            }
            $targets = $valid->all();
        }

        $a = DB::transaction(function () use ($data, $mode, $targets, $request) {
            $a = ServiceAnnouncement::create([
                'title' => $data['title'], 'body' => $data['body'], 'target_mode' => $mode,
                'network_incident_id' => $data['network_incident_id'] ?? null, 'visible_until' => $data['visible_until'] ?? null,
                'send_whatsapp' => $request->boolean('send_whatsapp'), 'show_in_portal' => $request->boolean('show_in_portal'),
                'status' => 'draft', 'created_by' => Auth::guard('web')->id(),
            ]);
            if (in_array($mode, ['odp', 'odc', 'device', 'package'], true)) {
                foreach ($targets as $id) {
                    $a->targets()->create(['target_type' => $mode, 'target_id' => $id]);
                }
            }
            AuditLogger::log('announcement.created', $a, null, $a->only(['title', 'target_mode']));

            return $a;
        });

        return redirect()->route('app.announcements.show', $a)->with('success', 'Draf pengumuman dibuat. Periksa daftar penerima sebelum mengirim.');
    }

    public function show(ServiceAnnouncement $announcement, AnnouncementService $service)
    {
        $announcement->load(['targets', 'incident']);

        return view('isp.announcements.show', [
            'a' => $announcement,
            'recipients' => $announcement->status === 'draft' ? $service->recipients($announcement) : collect(),
            'deliveries' => $announcement->status !== 'draft' ? $announcement->deliveries()->with(['customer', 'log'])->paginate(50) : null,
        ]);
    }

    public function send(ServiceAnnouncement $announcement, AnnouncementService $service)
    {
        try {
            $n = $service->send($this->tenant(), $announcement->load(['targets', 'incident']));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', "Pengumuman dikirim ke {$n} pelanggan (WhatsApp diantre dengan rate limit).");
    }
}
