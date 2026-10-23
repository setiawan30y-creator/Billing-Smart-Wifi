<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppMessage;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pengaturan → WhatsApp → Template Pesan.
 */
class NotificationTemplateController extends Controller
{
    public function index()
    {
        $existing = NotificationTemplate::where('channel', 'whatsapp')->get()->keyBy('event');
        // Pastikan semua event memiliki template (default) untuk tenant ini.
        foreach (NotificationTemplate::DEFAULTS as $event => $body) {
            if (! $existing->has($event)) {
                $existing[$event] = NotificationTemplate::create(['event' => $event, 'channel' => 'whatsapp', 'body' => $body, 'is_enabled' => true]);
            }
        }

        return view('isp.templates.index', [
            'templates' => collect(NotificationTemplate::EVENTS)->map(fn ($label, $event) => $existing[$event]),
            'driver' => config('almara.whatsapp.driver'),
        ]);
    }

    public function edit(NotificationTemplate $template)
    {
        return view('isp.templates.edit', [
            'template' => $template,
            'preview' => NotificationTemplate::render($template->body, NotificationService::sampleData($this->tenant())),
        ]);
    }

    public function update(Request $request, NotificationTemplate $template)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        if ($unknown = NotificationTemplate::unknownPlaceholders($data['body'])) {
            return back()->withInput()->withErrors(['body' => 'Placeholder tidak dikenal: {'.implode('}, {', $unknown).'}']);
        }
        $original = $template->getOriginal();
        $template->update(['body' => $data['body'], 'is_enabled' => $request->boolean('is_enabled')]);
        AuditLogger::changes('template.updated', $template, $original);

        return redirect()->route('app.templates.index')->with('success', 'Template disimpan.');
    }

    public function preview(Request $request)
    {
        $body = (string) $request->input('body');

        return response()->json([
            'preview' => NotificationTemplate::render($body, NotificationService::sampleData($this->tenant())),
            'unknown' => NotificationTemplate::unknownPlaceholders($body),
        ]);
    }

    public function testSend(Request $request, NotificationTemplate $template)
    {
        $data = $request->validate(['number' => ['required', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/']]);
        $log = NotificationLog::create([
            'event' => $template->event,
            'channel' => 'whatsapp',
            'recipient' => NotificationService::normalizeNumber($data['number']),
            'message' => '[TES] '.NotificationTemplate::render($template->body, NotificationService::sampleData($this->tenant())),
            'dedupe_key' => 'test:'.Str::uuid(),
            'status' => 'queued',
        ]);
        SendWhatsAppMessage::dispatch($this->tenant()->id, $log->id);
        AuditLogger::log('template.test_send', $template, null, ['to' => $log->recipient]);

        return back()->with('success', 'Pesan tes masuk antrean. Lihat status di Log Pengiriman.');
    }

    public function logs(Request $request)
    {
        return view('isp.templates.logs', [
            'logs' => NotificationLog::when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest()->paginate(30)->withQueryString(),
        ]);
    }
}
