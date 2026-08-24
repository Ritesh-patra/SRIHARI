<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Consumer;
use App\Models\ConsumerSurvey;
use App\Models\DtrSurvey;
use App\Models\Pole;
use App\Rules\ClientImageFile;
use App\Support\SurveyPhotoStorage;
use App\Support\SurveyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ConsumerSurveyController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $surveys = SurveyScope::apply(
            DtrSurvey::with(['dtr.poles', 'dtr.consumers'])
                // pending_approval kept for any legacy rows before DTR auto-approve-on-submit.
                ->whereIn('status', ['approved', 'pending_approval'])
                ->whereNull('consumer_survey_completed_at'),
            $user
        )->latest()->paginate(12);

        return view('consumer.index', compact('surveys'));
    }

    public function poles(DtrSurvey $survey)
    {
        $this->authorizeApproved($survey);

        $poles = Pole::withCount('consumers')
            ->withCount(['consumerSurveys as surveyed_count' => function ($q) use ($survey) {
                $q->where('dtr_survey_id', $survey->id)
                    ->where(function ($q) {
                        $q->whereNull('survey_flag')
                            ->orWhereNotIn('survey_flag', ['not_accessible']);
                    });
            }])
            ->where('dtr_id', $survey->dtr_id)
            ->orderBy('pole_no')
            ->get();

        $totalHouses = $poles->sum('houses_connected');
        $masterTotal = Consumer::where('dtr_id', $survey->dtr_id)->where('is_active', true)->count();
        $totalConsumers = max($masterTotal, (int) $totalHouses);
        $surveyedConsumers = ConsumerSurvey::where('dtr_survey_id', $survey->id)
            ->where(function ($q) {
                $q->whereNull('survey_flag')->orWhere('survey_flag', '!=', 'not_accessible');
            })
            ->count();
        $pendingConsumers = max(0, $totalConsumers - $surveyedConsumers);

        $stats = [
            'total_poles' => $poles->count(),
            'total_houses' => $totalHouses,
            'total_consumers' => $totalConsumers,
            'surveyed_consumers' => $surveyedConsumers,
            'pending_consumers' => $pendingConsumers,
        ];

        return view('consumer.poles', compact('survey', 'poles', 'totalHouses', 'stats'));
    }

    public function storePole(Request $request, DtrSurvey $survey)
    {
        $this->authorizeApproved($survey);
        // Only Field Executives create poles — Super Admin can delete via admin.poles
        abort_unless(
            Auth::user()?->isFieldExecutive() || Auth::user()?->isAdmin(),
            403,
            'Only Field Executives can create poles.'
        );

        $data = $request->validate([
            'pole_no' => ['required', 'string', 'max:50'],
            'source_type' => ['required', Rule::in(['dtr', 'previous_pole'])],
            'previous_pole_id' => ['nullable', 'exists:poles,id'],
            'houses_connected' => ['required', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        if ($data['source_type'] === 'previous_pole' && empty($data['previous_pole_id'])) {
            return back()->withErrors(['previous_pole_id' => 'Select previous pole.']);
        }

        if ($data['source_type'] === 'dtr') {
            $data['previous_pole_id'] = null;
        }

        $data['dtr_id'] = $survey->dtr_id;
        $pole = Pole::create($data);
        ActivityLog::record('pole.created', $pole);

        return redirect()
            ->route('consumer.consumers', [$survey, $pole])
            ->with('success', "Pole {$pole->pole_no} added · {$pole->houses_connected} houses connected.");
    }

    public function consumers(DtrSurvey $survey, Pole $pole, Request $request)
    {
        $this->authorizeApproved($survey);
        abort_unless($pole->dtr_id === $survey->dtr_id, 404);

        $masterConsumers = Consumer::where('dtr_id', $survey->dtr_id)
            ->where(function ($q) use ($pole) {
                $q->whereNull('pole_id')->orWhere('pole_id', $pole->id);
            })
            ->when($request->q, function ($q, $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('ivrs', 'like', "%{$term}%")
                        ->orWhere('msn', 'like', "%{$term}%")
                        ->orWhere('account_no', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->get();

        $savedSurveys = ConsumerSurvey::where('dtr_survey_id', $survey->id)
            ->where('pole_id', $pole->id)
            ->latest()
            ->get();

        $summary = [
            'saved' => $savedSurveys->where('status', 'saved')->count(),
            'not_accessible' => $savedSurveys->where('survey_flag', 'not_accessible')->count(),
            'pdc' => $savedSurveys->where('survey_flag', 'pdc')->count(),
            'new' => $savedSurveys->where('survey_flag', 'new')->count(),
        ];

        return view('consumer.consumers', compact('survey', 'pole', 'masterConsumers', 'savedSurveys', 'summary'));
    }

    public function createConsumer(DtrSurvey $survey, Pole $pole, ?Consumer $consumer = null)
    {
        $this->authorizeApproved($survey);
        $this->authorizeSurveyor();
        abort_unless($pole->dtr_id === $survey->dtr_id, 404);

        return view('consumer.verify', compact('survey', 'pole', 'consumer'));
    }

    public function storeConsumer(Request $request, DtrSurvey $survey, Pole $pole)
    {
        $this->authorizeApproved($survey);
        $this->authorizeSurveyor();
        abort_unless($pole->dtr_id === $survey->dtr_id, 404);

        $data = $request->validate([
            'consumer_id' => ['nullable', 'exists:consumers,id'],
            'consumer_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'ivrs' => ['nullable', 'string', 'max:50'],
            'msn' => ['nullable', 'string', 'max:50'],
            'phase' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'meter_photo' => ['nullable', 'file', 'max:5120', new ClientImageFile],
            'update_houses_connected' => ['nullable', 'integer', 'min:0'],
            'survey_flag' => ['nullable', Rule::in(['new', 'not_accessible', 'pdc'])],
        ]);

        if ($request->filled('update_houses_connected')) {
            $pole->update(['houses_connected' => (int) $request->update_houses_connected]);
        }

        // Master consumer identity fields stay source-of-truth; survey copies live on consumer_surveys only.
        if (! empty($data['consumer_id'])) {
            Consumer::where('id', $data['consumer_id'])->update([
                'pole_id' => $pole->id,
            ]);
        } else {
            $data['survey_flag'] = $data['survey_flag'] ?? 'new';
        }

        $row = new ConsumerSurvey($data);
        $row->dtr_survey_id = $survey->id;
        $row->surveyor_id = Auth::id();
        $row->dtr_id = $survey->dtr_id;
        $row->pole_id = $pole->id;
        $row->surveyed_at = now();
        $row->status = ($data['survey_flag'] ?? null) === 'not_accessible' ? 'not_accessible' : 'saved';
        $row->survey_flag = $data['survey_flag'] ?? null;

        if ($request->hasFile('meter_photo')) {
            $row->meter_photo = SurveyPhotoStorage::store($request->file('meter_photo'), 'surveys/consumers');
        }

        $row->save();
        ActivityLog::record('consumer.survey_saved', $row);

        return redirect()
            ->route('consumer.consumers', [$survey, $pole])
            ->with('success', 'Consumer saved · Phone: '.$row->phone);
    }

    public function finish(DtrSurvey $survey)
    {
        $this->authorizeApproved($survey);
        $this->authorizeSurveyor();

        $survey->update([
            'consumer_survey_completed_at' => now(),
        ]);

        ActivityLog::record('consumer.survey_finished', $survey);

        return redirect()
            ->route('dashboard')
            ->with('success', 'DTR Consumer Survey completed for '.$survey->dtr_name);
    }

    public function dtrStatus(Request $request)
    {
        $user = Auth::user();
        $status = $request->query('status', 'all');

        $base = SurveyScope::apply(
            DtrSurvey::with(['dtr', 'surveyor', 'consumerSurveys']),
            $user
        );

        $groups = [
            'pending' => (clone $base)->where('status', 'pending_approval')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'approved' => (clone $base)->where('status', 'approved')->whereNull('consumer_survey_completed_at')->count(),
            'completed' => (clone $base)->whereNotNull('consumer_survey_completed_at')->count(),
        ];

        $query = clone $base;
        match ($status) {
            'pending' => $query->where('status', 'pending_approval'),
            'rejected' => $query->where('status', 'rejected'),
            'approved' => $query->where('status', 'approved')->whereNull('consumer_survey_completed_at'),
            'completed' => $query->whereNotNull('consumer_survey_completed_at'),
            default => $query->where(function ($q) {
                $q->whereIn('status', ['pending_approval', 'rejected', 'approved'])
                    ->orWhereNotNull('consumer_survey_completed_at');
            }),
        };

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('dtr_name', 'like', "%{$search}%")
                    ->orWhere('dtr_code', 'like', "%{$search}%")
                    ->orWhere('feeder_name', 'like', "%{$search}%");
            });
        }

        $surveys = $query->latest()->paginate(15)->withQueryString();

        return view('consumer.dtr-status', compact('surveys', 'groups', 'status'));
    }

    public function searchConsumers(Request $request)
    {
        $q = $request->query('q', '');
        $dtrId = $request->query('dtr_id');

        $items = Consumer::query()
            ->when($dtrId, fn ($query) => $query->where('dtr_id', $dtrId))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('ivrs', 'like', "%{$q}%")
                        ->orWhere('msn', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'ivrs', 'msn', 'pole_id']);

        return response()->json($items);
    }

    private function authorizeApproved(DtrSurvey $survey): void
    {
        $user = Auth::user();
        if (! $survey->isApprovedForConsumerSurvey()) {
            abort(403, 'Only submitted DTRs (not finished) can be surveyed.');
        }
        abort_unless(SurveyScope::canView($user, $survey), 403);
    }

    private function authorizeSurveyor(): void
    {
        $user = Auth::user();
        if (! ($user->isFieldExecutive() || $user->isAdmin())) {
            abort(403);
        }
    }
}
