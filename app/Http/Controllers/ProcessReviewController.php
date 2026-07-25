<?php

namespace App\Http\Controllers;

use App\Models\ExportProcess;
use App\Models\ProcessReview;
use Illuminate\Http\Request;

class ProcessReviewController extends Controller
{
    /**
     * Client submits or updates their review for a contract.
     */
    public function store(Request $request, ExportProcess $process)
    {
        $this->authorizeClientAccess($process);

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $user = auth()->user();

        // Check finalized
        if (!str_contains(strtolower($process->status ?? ''), 'finalizado')) {
            return back()->with('error', 'A avaliação só está disponível após o processo ser finalizado.');
        }

        // Upsert: one review per user per contract
        $review = ProcessReview::updateOrCreate(
            ['export_process_id' => $process->id, 'user_id' => $user->id],
            [
                'rating'  => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                // Reset admin reply if client re-evaluates
                'admin_reply' => null,
                'replied_by'  => null,
                'replied_at'  => null,
            ]
        );

        return back()->with('success', 'Avaliação enviada com sucesso! Obrigado pelo seu feedback.');
    }

    /**
     * Admin replies to a client review.
     */
    public function reply(Request $request, ProcessReview $review)
    {
        abort_unless(auth()->user()->is_master, 403, 'Acesso negado.');

        $validated = $request->validate([
            'admin_reply' => 'required|string|max:3000',
        ]);

        $review->update([
            'admin_reply' => $validated['admin_reply'],
            'replied_by'  => auth()->id(),
            'replied_at'  => now(),
        ]);

        return back()->with('success', 'Resposta enviada ao cliente.');
    }

    /**
     * Admin: list all reviews with metrics.
     */
    public function adminIndex(Request $request)
    {
        abort_unless(auth()->user()->is_master, 403);

        $query = ProcessReview::with([
            'user:id,name,email',
            'process:id,contract_number,status',
            'repliedBy:id,name',
        ])->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('process', fn($p) => $p->where('contract_number', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->paginate(20)->withQueryString();

        // Metrics
        $totalReviews    = ProcessReview::count();
        $avgRating       = round(ProcessReview::avg('rating') ?? 0, 2);
        $pendingReplies  = ProcessReview::whereNull('admin_reply')->count();
        $withProblems    = ProcessReview::where('rating', '<=', 2)->count();

        // Top clients by review count
        $topReviewers = ProcessReview::selectRaw('user_id, count(*) as total, avg(rating) as avg_rating')
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Clients with most problems (rating ≤ 2)
        $problemClients = ProcessReview::selectRaw('user_id, count(*) as total')
            ->with('user:id,name,email')
            ->where('rating', '<=', 2)
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Rating distribution
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = ProcessReview::where('rating', $i)->count();
        }

        return \Inertia\Inertia::render('Admin/Reviews/Index', [
            'reviews'        => $reviews,
            'filters'        => $request->only(['search', 'rating']),
            'metrics'        => compact('totalReviews', 'avgRating', 'pendingReplies', 'withProblems'),
            'topReviewers'   => $topReviewers,
            'problemClients' => $problemClients,
            'distribution'   => $distribution,
        ]);
    }

    /**
     * Admin: view single review detail.
     */
    public function adminShow(ProcessReview $review)
    {
        abort_unless(auth()->user()->is_master, 403);

        $review->load([
            'user:id,name,email',
            'process:id,contract_number,status,product_id',
            'process.product:id,name',
            'repliedBy:id,name',
        ]);

        return \Inertia\Inertia::render('Admin/Reviews/Show', [
            'review' => $review,
        ]);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function authorizeClientAccess(ExportProcess $process): void
    {
        $user = auth()->user();
        if ($user->is_master) return;

        $linked = $process->users()->where('users.id', $user->id)->exists();
        if (!$linked) abort(403, 'Você não tem acesso a este contrato.');
    }
}
