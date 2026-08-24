<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Circle;
use App\Models\Division;
use App\Models\Dtr;
use App\Models\DtrSurvey;
use App\Models\Feeder;
use App\Models\Region;
use App\Models\Substation;
use App\Models\Zone;
use App\Rules\ClientImageFile;
use App\Support\SurveyPhotoStorage;
use App\Support\SurveyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DtrSurveyController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $base = SurveyScope::apply(DtrSurvey::query()->with(['surveyor', 'supervisor']), $user);

        $surveys = (clone $base)->latest()->paginate(12);

        $stats = [
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'pending' => (clone $base)->where('status', 'pending_approval')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        return view('surveys.index', compact('surveys', 'stats'));
    }

    public function create()
    {
        abort_unless(Auth::user()->canCaptureSurveys(), 403);

        return view('surveys.create', [
            'regions' => Region::where('is_active', true)->orderBy('name')->get(),
            'dtrConditions' => ['Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other'],
            'smartMeterStatuses' => ['Installed', 'Not Installed', 'Meter Missing'],
            'oldMeterConditions' => ['Healthy', 'Faulty', 'Burnt', 'Display Off', 'Missing', 'Removed'],
            'newMeterMakes' => ['L&T Schneider', 'HPL', 'Visiontek'],
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()->canCaptureSurveys(), 403);
        $data = $this->validated($request);
        $user = Auth::user();

        $feeder = Feeder::findOrFail($data['feeder_id']);
        $dtr = Dtr::findOrFail($data['dtr_id']);

        $survey = new DtrSurvey($data);
        $survey->surveyor_id = $user->id;
        $survey->supervisor_id = $user->supervisor_id;
        $survey->surveyed_at = now();
        $survey->feeder_code = $feeder->code;
        $survey->feeder_name = $feeder->name;
        $survey->dtr_code = $dtr->code;
        $survey->dtr_name = $dtr->name;
        $isSubmit = $request->input('action') === 'submit';
        // DTR submit requires manager approval (auto-approve disabled).
        if ($isSubmit) {
            $survey->status = 'pending_approval';
            $survey->reviewed_at = null;
            $survey->locked_at = null;
        } else {
            $survey->status = 'draft';
            $survey->reviewed_at = null;
            $survey->locked_at = null;
        }

        if ($request->hasFile('dtr_overall_photo')) {
            $survey->dtr_overall_photo = SurveyPhotoStorage::store($request->file('dtr_overall_photo'), 'surveys/dtr');
        }
        if ($request->hasFile('smart_meter_photo')) {
            $survey->smart_meter_photo = SurveyPhotoStorage::store($request->file('smart_meter_photo'), 'surveys/meters');
        }

        if ($isSubmit) {
            $this->assertPhotos($survey);
        }

        if ($data['smart_meter_status'] !== 'Not Installed') {
            $survey->old_meter_condition = null;
            $survey->old_msn = null;
            $survey->old_meter_make = null;
        }

        $survey->save();

        ActivityLog::record($isSubmit ? 'survey.submitted' : 'survey.draft', $survey);

        return redirect()
            ->route('surveys.show', $survey)
            ->with('success', $isSubmit
                ? 'DTR survey submitted. You can start Consumer Survey.'
                : 'Draft saved successfully.');
    }

    public function show(DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canView(Auth::user(), $survey), 403);
        $survey->load(['surveyor', 'supervisor', 'region', 'circle', 'division', 'zone', 'substation']);

        return view('surveys.show', [
            'survey' => $survey,
            'canApprove' => SurveyScope::canApprove(Auth::user(), $survey),
        ]);
    }

    public function edit(DtrSurvey $survey)
    {
        $this->authorizeEdit($survey);

        return view('surveys.edit', [
            'survey' => $survey,
            'regions' => Region::where('is_active', true)->orderBy('name')->get(),
            'circles' => Circle::where('region_id', $survey->region_id)->orderBy('name')->get(),
            'divisions' => Division::where('circle_id', $survey->circle_id)->orderBy('name')->get(),
            'zones' => Zone::where('division_id', $survey->division_id)->orderBy('name')->get(),
            'substations' => Substation::where('zone_id', $survey->zone_id)->orderBy('name')->get(),
            'feeders' => Feeder::where('substation_id', $survey->substation_id)->orderBy('name')->get(),
            'dtrs' => Dtr::where('feeder_id', $survey->feeder_id)->orderBy('name')->get(),
            'dtrConditions' => ['Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other'],
            'smartMeterStatuses' => ['Installed', 'Not Installed', 'Meter Missing'],
            'oldMeterConditions' => ['Healthy', 'Faulty', 'Burnt', 'Display Off', 'Missing', 'Removed'],
            'newMeterMakes' => ['L&T Schneider', 'HPL', 'Visiontek'],
        ]);
    }

    public function update(Request $request, DtrSurvey $survey)
    {
        $this->authorizeEdit($survey);
        $data = $this->validated($request, $survey);

        $feeder = Feeder::findOrFail($data['feeder_id']);
        $dtr = Dtr::findOrFail($data['dtr_id']);

        $survey->fill($data);
        $survey->feeder_code = $feeder->code;
        $survey->feeder_name = $feeder->name;
        $survey->dtr_code = $dtr->code;
        $survey->dtr_name = $dtr->name;
        $survey->review_remarks = null;
        $isSubmit = $request->input('action') === 'submit';
        if ($isSubmit) {
            $survey->status = 'pending_approval';
            $survey->reviewed_at = null;
            $survey->locked_at = null;
        } else {
            $survey->status = 'draft';
            $survey->reviewed_at = null;
            $survey->locked_at = null;
        }

        if ($request->hasFile('dtr_overall_photo')) {
            $survey->dtr_overall_photo = SurveyPhotoStorage::store($request->file('dtr_overall_photo'), 'surveys/dtr');
        }
        if ($request->hasFile('smart_meter_photo')) {
            $survey->smart_meter_photo = SurveyPhotoStorage::store($request->file('smart_meter_photo'), 'surveys/meters');
        }

        if ($isSubmit) {
            $this->assertPhotos($survey);
        }

        if ($data['smart_meter_status'] !== 'Not Installed') {
            $survey->old_meter_condition = null;
            $survey->old_msn = null;
            $survey->old_meter_make = null;
        }

        $survey->save();
        ActivityLog::record($isSubmit ? 'survey.resubmitted' : 'survey.updated', $survey);

        if ($isSubmit) {
            AppNotification::clearForSubject(
                (int) $survey->surveyor_id,
                DtrSurvey::class,
                (int) $survey->id
            );
        }

        return redirect()
            ->route('surveys.show', $survey)
            ->with('success', $isSubmit
                ? 'DTR survey re-submitted. You can start Consumer Survey.'
                : 'Draft updated successfully.');
    }

    public function approve(Request $request, DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canApprove(Auth::user(), $survey), 403);
        $request->validate(['review_remarks' => ['nullable', 'string', 'max:1000']]);

        $survey->update([
            'status' => 'approved',
            'review_remarks' => $request->review_remarks,
            'reviewed_at' => now(),
            'locked_at' => now(),
            'supervisor_id' => $survey->supervisor_id ?: Auth::id(),
        ]);

        ActivityLog::record('survey.approved', $survey, ['by' => Auth::id()]);
        AppNotification::notifyUser(
            (int) $survey->surveyor_id,
            'Survey approved',
            $survey->dtr_name.' is approved. You can start Consumer Survey.',
            route('consumer.poles', $survey),
            DtrSurvey::class,
            (int) $survey->id
        );

        return back()->with('success', 'Survey approved.');
    }

    public function reject(Request $request, DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canApprove(Auth::user(), $survey), 403);
        $data = $request->validate(['review_remarks' => ['required', 'string', 'max:1000']]);

        $survey->update([
            'status' => 'rejected',
            'review_remarks' => $data['review_remarks'],
            'reviewed_at' => now(),
            'locked_at' => null,
        ]);

        ActivityLog::record('survey.rejected', $survey, ['reason' => $data['review_remarks']]);
        AppNotification::notifyUser(
            (int) $survey->surveyor_id,
            'Survey rejected — re-survey required',
            'DTR '.$survey->dtr_name.' ('.$survey->dtr_code.') survey was rejected. Please re-survey this DTR and submit again. Reason: '.$data['review_remarks'],
            route('surveys.edit', $survey),
            DtrSurvey::class,
            (int) $survey->id
        );

        return back()->with('success', 'Survey rejected. Field Executive can edit and resubmit.');
    }

    public function pending()
    {
        $user = Auth::user();
        abort_unless($user->canApproveSurveys(), 403);

        $surveys = SurveyScope::apply(
            DtrSurvey::with('surveyor')->where('status', 'pending_approval'),
            $user
        )->latest()->paginate(12);

        return view('surveys.pending', compact('surveys'));
    }

    private function notifyApprovers(DtrSurvey $survey): void
    {
        $managerIds = \App\Models\User::query()
            ->whereIn('role', ['manager', 'project_manager', 'admin', 'super_admin'])
            ->where('is_active', true)
            ->pluck('id');

        foreach ($managerIds as $id) {
            if ((int) $id === (int) $survey->surveyor_id) {
                continue;
            }
            AppNotification::notifyUser(
                $id,
                'New survey pending approval',
                $survey->dtr_name.' · '.$survey->feeder_name,
                route('surveys.show', $survey)
            );
        }
    }

    private function validated(Request $request, ?DtrSurvey $survey = null): array
    {
        $isSubmit = $request->input('action') === 'submit';

        if ($request->filled('lt_line_type')) {
            $normalized = \App\Support\LtLineType::normalize($request->input('lt_line_type'));
            if ($normalized !== null) {
                $request->merge(['lt_line_type' => $normalized]);
            }
        }

        $rules = [
            'region_id' => ['required', 'exists:regions,id'],
            'circle_id' => ['required', 'exists:circles,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'zone_id' => ['required', 'exists:zones,id'],
            'substation_id' => ['required', 'exists:substations,id'],
            'feeder_id' => ['required', 'exists:feeders,id'],
            'dtr_id' => ['required', 'exists:dtrs,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'dtr_capacity_kva' => ['nullable', 'integer', 'min:1'],
            'dtr_condition' => ['required', Rule::in(['Normal', 'Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other'])],
            'lt_line_type' => ['nullable', Rule::in(\App\Support\LtLineType::options())],
            'smart_meter_status' => ['required', Rule::in(['Installed', 'Not Installed', 'Meter Missing'])],
            'old_meter_condition' => ['nullable', Rule::in(['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'])],
            'old_msn' => ['nullable', 'string', 'max:100'],
            'old_meter_make' => ['nullable', 'string', 'max:100'],
            'new_msn' => ['nullable', 'string', 'max:100'],
            'new_meter_make' => ['nullable', Rule::in(['L&T Schneider', 'LNT', 'HPL', 'Visiontek'])],
            'new_meter_ct_ratio' => ['nullable', 'string', 'max:50'],
            'new_meter_mf' => ['nullable', 'string', 'max:50'],
            'observation' => ['nullable', 'string', 'max:500'],
            'dtr_overall_photo' => [$survey?->dtr_overall_photo ? 'nullable' : ($isSubmit ? 'required' : 'nullable'), 'file', 'max:5120', new ClientImageFile],
            'smart_meter_photo' => [$survey?->smart_meter_photo ? 'nullable' : ($isSubmit ? 'required' : 'nullable'), 'file', 'max:5120', new ClientImageFile],
        ];

        if ($request->input('smart_meter_status') === 'Not Installed') {
            $rules['old_meter_condition'] = ['required', Rule::in(['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'])];
            $rules['old_msn'] = ['required', 'string', 'max:100'];
            $rules['old_meter_make'] = ['required', 'string', 'max:100'];
        }

        if (in_array($request->input('smart_meter_status'), ['Installed', 'Not Installed'], true) && $isSubmit) {
            $rules['new_msn'] = ['required', 'string', 'max:100'];
            $rules['new_meter_make'] = ['required', Rule::in(['L&T Schneider', 'LNT', 'HPL', 'Visiontek'])];
            $rules['new_meter_ct_ratio'] = ['required', 'string', 'max:50'];
            $rules['new_meter_mf'] = ['required', 'string', 'max:50'];
        }

        return $request->validate($rules);
    }

    private function assertPhotos(DtrSurvey $survey): void
    {
        if (! $survey->dtr_overall_photo || ! $survey->smart_meter_photo) {
            abort(422, 'Both DTR Overall Photo and Smart Meter Photo are mandatory for submission.');
        }
    }

    private function authorizeEdit(DtrSurvey $survey): void
    {
        $user = Auth::user();
        if ($user->isAdmin()) {
            return;
        }
        if (! $user->isFieldExecutive() || $survey->surveyor_id !== $user->id || ! $survey->isEditable()) {
            abort(403);
        }
    }
}
