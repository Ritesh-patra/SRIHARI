<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\Consumer;
use App\Models\ConsumerSurvey;
use App\Models\Dtr;
use App\Models\DtrSurvey;
use App\Models\Feeder;
use App\Models\Pole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $consumerTotal = Consumer::count();
        $consumerSurveyed = (int) ConsumerSurvey::query()
            ->whereNotNull('ivrs')
            ->where('ivrs', '!=', '')
            ->distinct()
            ->count('ivrs');
        if ($consumerSurveyed === 0) {
            $consumerSurveyed = min($consumerTotal, ConsumerSurvey::count());
        }
        $consumerRemaining = max(0, $consumerTotal - $consumerSurveyed);

        $dtrTotal = Dtr::count();
        $dtrSurveyed = (int) DtrSurvey::query()
            ->whereIn('status', ['pending_approval', 'approved', 'completed'])
            ->whereNotNull('dtr_id')
            ->distinct()
            ->count('dtr_id');
        $dtrRemaining = max(0, $dtrTotal - $dtrSurveyed);

        $poleTotal = Pole::count();
        $poleSurveyed = (int) Pole::query()->has('consumerSurveys')->count();
        $poleRemaining = max(0, $poleTotal - $poleSurveyed);

        $browseRange = [
            'view' => 1,
            'status' => 'all',
            'from' => now()->subYear()->toDateString(),
            'to' => now()->toDateString(),
        ];

        $progressTiles = [
            [
                'label' => 'Consumer',
                'done' => $consumerSurveyed,
                'remaining' => $consumerRemaining,
                'total' => $consumerTotal,
                'href' => route('consumer-approval.index', $browseRange),
                'tone' => 'text-sky-400',
            ],
            [
                'label' => 'DTR',
                'done' => $dtrSurveyed,
                'remaining' => $dtrRemaining,
                'total' => $dtrTotal,
                'href' => route('dtr-surveys.index', $browseRange),
                'tone' => 'text-emerald-400',
            ],
            [
                'label' => 'Pole',
                'done' => $poleSurveyed,
                'remaining' => $poleRemaining,
                'total' => $poleTotal,
                'href' => route('pole-surveys.index', $browseRange),
                'tone' => 'text-amber-400',
            ],
        ];

        $stats = [
            'users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'field_executives' => User::where('role', User::ROLE_FIELD_EXECUTIVE)->count(),
            'managers' => User::whereIn('role', [User::ROLE_MANAGER, User::ROLE_PROJECT_MANAGER])->count(),
            'feeders' => Feeder::count(),
            'dtrs' => $dtrTotal,
            'consumers' => $consumerTotal,
            'pending' => DtrSurvey::where('status', 'pending_approval')->count(),
            'approved' => DtrSurvey::where('status', 'approved')->whereNull('consumer_survey_completed_at')->count(),
            'rejected' => DtrSurvey::where('status', 'rejected')->count(),
            'draft' => DtrSurvey::where('status', 'draft')->count(),
            'completed' => DtrSurvey::whereNotNull('consumer_survey_completed_at')->count(),
            'consumer_surveys' => ConsumerSurvey::count(),
            'unread_notifications' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'consumer_surveyed' => $consumerSurveyed,
            'consumer_remaining' => $consumerRemaining,
            'dtr_surveyed' => $dtrSurveyed,
            'dtr_remaining' => $dtrRemaining,
            'pole_surveyed' => $poleSurveyed,
            'pole_remaining' => $poleRemaining,
        ];

        $statusChart = [
            'labels' => ['Draft', 'Pending', 'Approved', 'Rejected', 'Completed'],
            'values' => [
                $stats['draft'],
                $stats['pending'],
                $stats['approved'],
                $stats['rejected'],
                $stats['completed'],
            ],
        ];

        $roleChart = [
            'labels' => ['Super Admin', 'Project Manager', 'Manager', 'Field Executive'],
            'values' => [
                User::where('role', User::ROLE_SUPER_ADMIN)->count(),
                User::where('role', User::ROLE_PROJECT_MANAGER)->count(),
                User::where('role', User::ROLE_MANAGER)->count(),
                User::where('role', User::ROLE_FIELD_EXECUTIVE)->count(),
            ],
        ];

        // Last 14 days survey submissions
        $trendLabels = [];
        $trendValues = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $trendLabels[] = $day->format('d M');
            $trendValues[] = DtrSurvey::whereDate('created_at', $day)->count();
        }
        $trendChart = ['labels' => $trendLabels, 'values' => $trendValues];

        $recentUsers = User::latest()->take(5)->get();
        $recentSurveys = DtrSurvey::with('surveyor')->latest()->take(6)->get();
        $notifications = AppNotification::where('user_id', $user->id)->latest()->take(5)->get();

        return view('dashboard', compact(
            'stats',
            'progressTiles',
            'recentUsers',
            'recentSurveys',
            'notifications',
            'statusChart',
            'roleChart',
            'trendChart'
        ));
    }
}
