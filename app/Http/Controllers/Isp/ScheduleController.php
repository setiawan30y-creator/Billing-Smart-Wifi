<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\TechnicianAvailability;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kalender teknisi (bulanan) berdasarkan jadwal work order & ketersediaan teknisi.
 */
class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $tz = $this->tenant()->timezone;
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now($tz)->format('Y-m');
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfDay();
        $gridStart = $start->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $start->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $user = $request->user();
        $manage = $user->can('work_orders.manage');
        $tech = $manage ? $request->integer('tech') : $user->id;

        $wos = WorkOrder::with(['technicians', 'customer'])
            ->whereNotNull('scheduled_at')->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_at', [$gridStart, $gridEnd->endOfDay()])
            ->when($tech, fn ($q) => $q->assignedTo($tech))
            ->orderBy('scheduled_at')->get()
            ->groupBy(fn ($w) => $w->scheduled_at->timezone($tz)->toDateString());

        $avail = TechnicianAvailability::with('user')->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when($tech, fn ($q) => $q->where('user_id', $tech))->get()->groupBy(fn ($a) => $a->date->toDateString());

        return view('isp.work-orders.calendar', [
            'month' => $start, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'wos' => $wos, 'avail' => $avail,
            'technicians' => User::forCurrentTenant()->where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('slug', ['technician', 'noc', 'isp_admin']))->orderBy('name')->get(['id', 'name']),
            'tech' => $tech, 'manage' => $manage, 'tz' => $tz,
        ]);
    }

    public function availability(Request $request)
    {
        $ids = User::forCurrentTenant()->pluck('id')->all();
        $data = $request->validate([
            'user_id' => ['required', Rule::in($ids)],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_merge(array_keys(TechnicianAvailability::STATUSES), ['available']))],
            'note' => ['nullable', 'string', 'max:190'],
        ]);
        if ($data['status'] === 'available') {
            TechnicianAvailability::where('user_id', $data['user_id'])->whereDate('date', $data['date'])->delete();
        } else {
            TechnicianAvailability::updateOrCreate(['user_id' => $data['user_id'], 'date' => $data['date']], ['status' => $data['status'], 'note' => $data['note'] ?? null]);
        }
        AuditLogger::log('schedule.availability', null, null, $data);

        return back()->with('success', 'Ketersediaan teknisi disimpan.');
    }
}
