<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReportUpdatedNotification;

class AdminController extends Controller
{
    /**
     * Display the users management page.
     */
    public function usersIndex(Request $request)
    {
        $query = User::latest();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        return \Inertia\Inertia::render('Admin/Users/Index', [
            'users' => $query->get(),
            'filters' => $request->only(['search', 'status']),
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count()
        ]);
    }

    /**
     * Display user activity analytics.
     */
    public function activityIndex()
    {
        $usersPaginated = User::orderByRaw('last_activity_at IS NULL, last_activity_at DESC')
            ->paginate(12)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_master' => $user->is_master,
                    'is_online' => $user->last_activity_at && $user->last_activity_at->gt(now()->subMinutes(5)),
                    'last_login' => $user->last_login_at ? $user->last_login_at->diffForHumans() : __('Never'),
                    'last_activity' => $user->last_activity_at ? $user->last_activity_at->diffForHumans() : __('None'),
                    'notified_at' => $user->email_notified_at ? $user->email_notified_at->diffForHumans() : null,
                    'clicked_at' => $user->email_clicked_at ? $user->email_clicked_at->diffForHumans() : null,
                ];
            });

        return \Inertia\Inertia::render('Admin/Activity/Index', [
            'users' => $usersPaginated,
            'total_users' => User::count(),
            'online_users' => User::where('last_activity_at', '>', now()->subMinutes(5))->count()
        ]);
    }

    /**
     * Clear all user activity metadata.
     */
    public function bulkClearActivity()
    {
        User::query()->update([
            'last_login_at' => null,
            'last_activity_at' => null,
            'email_notified_at' => null,
            'email_clicked_at' => null,
        ]);

        return back()->with('success', __('Activities cleared successfully.'));
    }


    /**
     * Display the user creation page.
     */
    /**
     * Display the user creation page.
     */
    /**
     * Display the user creation page.
     */
    public function usersCreate()
    {
        return \Inertia\Inertia::render('Admin/Users/Create', [
            'default_pages' => \App\Models\Setting::get('default_user_pages', [])
        ]);
    }

    /**
     * Display the settings page.
     */
    public function settingsIndex()
    {
        $settings = \App\Models\Setting::all()->mapWithKeys(function ($item) {
            return [$item->key => \App\Models\Setting::get($item->key)];
        });

        return \Inertia\Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'users' => User::where('is_active', true)->select('id', 'name', 'email', 'is_master')->get(),
            'queue_stats' => [
                'pending' => \DB::table('jobs')->count(),
                'failed' => \DB::table('failed_jobs')->count(),
                'is_running' => \Cache::has('queue_worker_active')
            ]
        ]);
    }

    /**
     * Update system settings.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->settings as $key => $value) {
            \App\Models\Setting::set($key, $value);
        }

        return back()->with('success', __('Settings updated successfully.'));
    }

    /**
     * Store a new master admin or user.
     */
    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'is_master' => ['boolean'],
            'is_active' => ['boolean'],
            'allowed_pages' => ['nullable', 'array'],
            'tenant_id' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'company_name' => $request->company_name,
            'is_active' => $request->boolean('is_active', true),
            'tenant_id' => $request->input('tenant_id'),
            'allowed_pages' => $request->input('allowed_pages', []),
        ]);

        $user->is_master = $request->boolean('is_master', false);
        $user->save();

        return redirect()->route('admin.users.index')->with('success', __('User created successfully.'));
    }

    /**
     * Display the user edit page.
     */
    public function usersEdit(User $user)
    {
        return \Inertia\Inertia::render('Admin/Users/Edit', [
            'user' => $user
        ]);
    }

    /**
     * Update the specified user.
     */
    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'is_master' => ['boolean'],
            'is_active' => ['boolean'],
            'allowed_pages' => ['nullable', 'array'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($user->id === $request->user()->id) {
            if (!$request->boolean('is_master') || !$request->boolean('is_active')) {
                return back()->with('error', __('You cannot remove your own master status or deactivate yourself.'));
            }
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'company_name' => $request->company_name,
            'allowed_pages' => $request->input('allowed_pages', []),
        ];

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->fill($userData);
        $user->is_master = $request->boolean('is_master');
        $user->is_active = $request->boolean('is_active');
        $user->save();

        if ($user->id === $request->user()->id && $request->filled('password')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->to('/login')->with('success', __('User updated successfully. Please log in again.'));
        }

        return redirect()->route('admin.users.index')->with('success', __('User updated successfully.'));
    }

    /**
     * Get Power BI Pages (API)
     */
    public function getPowerBiPages(\App\Services\PowerBiService $powerBiService)
    {
        try {
            return response()->json($powerBiService->getReportPages());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Toggle user active status.
     */
    public function toggleUserStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot deactivate yourself.'));
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return back()->with('success', __('User status updated.'));
    }

    /**
     * Bulk update users status.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'is_active' => 'required|boolean',
        ]);

        $ids = $request->ids;
        $currentUserExcluded = false;

        if (!$request->is_active && in_array(auth()->id(), $ids)) {
            $ids = array_filter($ids, fn($id) => $id != auth()->id());
            $currentUserExcluded = true;
        }

        if (empty($ids)) {
            return back()->with('error', __('No changes allowed for your own account.'));
        }

        User::whereIn('id', $ids)->update([
            'is_active' => $request->is_active
        ]);

        $message = __('Users updated successfully.');
        if ($currentUserExcluded) {
            $message .= ' ' . __('Your account was not deactivated.');
        }

        return back()->with('success', $message);
    }

    /**
     * Remove the specified user.
     */
    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', __('You cannot delete yourself.'));
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', __('User deleted successfully.'));
    }

    /**
     * Notify users about report update.
     */
    public function notifyUpdate(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $users = User::whereIn('id', $request->user_ids)->get();

        $date = now()->format('d/m/Y');
        $time = now()->format('H:i');

        $delay = now();

        foreach ($users as $user) {
            Mail::to($user->email)->later($delay, new ReportUpdatedNotification($user, $date, $time));
            $user->update(['email_notified_at' => now()]);
            $delay = $delay->addSeconds(5); // Space of 5 seconds between emails
        }

        return back()->with('success', __('Notifications sent successfully.'));
    }

    /**
     * Track when a user clicks the email link.
     */
    public function trackEmailClick(User $user)
    {
        $user->update(['email_clicked_at' => now()]);

        return redirect()->route('dashboard');
    }

    /**
     * Send a test email to the current user.
     */
    public function testMail(Request $request)
    {
        $user = auth()->user();
        $date = now()->format('d/m/Y');
        $time = now()->format('H:i');

        try {
            Mail::to($user->email)->send(new ReportUpdatedNotification($user, $date, $time));
            return back()->with('success', __('Test email sent successfully to :email', ['email' => $user->email]));
        } catch (\Exception $e) {
            return back()->with('error', __('Failed to send test email: ') . $e->getMessage());
        }
    }

    /**
     * Get real-time queue status (API).
     */
    public function getQueueStatus()
    {
        return response()->json([
            'pending' => \DB::table('jobs')->count(),
            'failed' => \DB::table('failed_jobs')->count(),
            'is_running' => \Cache::has('queue_worker_active'),
            'last_check' => now()->toIso8601String()
        ]);
    }
}
