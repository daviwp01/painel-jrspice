<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractReview;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContractReviewController extends Controller
{
    public function store(Request $request, Contract $contract)
    {
        $this->authorizeClientAccess($contract);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        if (! str_contains(strtolower($contract->status ?? ''), 'finalizado')) {
            return back()->with('error', 'A avaliação só está disponível após o processo ser finalizado.');
        }

        ContractReview::updateOrCreate(
            ['contract_id' => $contract->id, 'user_id' => auth()->id()],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'admin_reply' => null,
                'replied_by' => null,
                'replied_at' => null,
            ]
        );

        return back()->with('success', 'Avaliação enviada com sucesso! Obrigado pelo seu feedback.');
    }

    public function reply(Request $request, ContractReview $review)
    {
        abort_unless(auth()->user()?->is_master, 403, 'Acesso negado.');

        $validated = $request->validate([
            'admin_reply' => 'required|string|max:3000',
        ]);

        $review->update([
            'admin_reply' => $validated['admin_reply'],
            'replied_by' => auth()->id(),
            'replied_at' => now(),
        ]);

        return back()->with('success', 'Resposta enviada ao cliente.');
    }

    public function adminIndex(Request $request)
    {
        abort_unless(auth()->user()?->is_master, 403);

        $query = ContractReview::query()
            ->with(['user:id,name,email', 'contract:id,contract_number,status', 'repliedBy:id,name'])
            ->latest();

        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $search))
                    ->orWhereHas('contract', fn ($c) => $c->where('contract_number', 'like', $search));
            });
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->integer('rating'));
        }

        $reviews = $query->paginate(20)->withQueryString();

        $metrics = [
            'totalReviews' => ContractReview::count(),
            'avgRating' => round((float) ContractReview::avg('rating'), 2),
            'pendingReplies' => ContractReview::whereNull('admin_reply')->count(),
            'withProblems' => ContractReview::where('rating', '<=', 2)->count(),
        ];

        $topReviewers = ContractReview::query()
            ->selectRaw('user_id, count(*) as total, avg(rating) as avg_rating')
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $problemClients = ContractReview::query()
            ->selectRaw('user_id, count(*) as total')
            ->with('user:id,name,email')
            ->where('rating', '<=', 2)
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = ContractReview::where('rating', $i)->count();
        }

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $request->only(['search', 'rating']),
            'metrics' => $metrics,
            'topReviewers' => $topReviewers,
            'problemClients' => $problemClients,
            'distribution' => $distribution,
        ]);
    }

    public function adminShow(ContractReview $review)
    {
        abort_unless(auth()->user()?->is_master, 403);

        $review->load([
            'user:id,name,email',
            'contract:id,contract_number,status',
            'contract.items:id,contract_id,product_id,quantity_tons',
            'contract.items.product:id,name',
            'repliedBy:id,name',
        ]);

        return Inertia::render('Admin/Reviews/Show', [
            'review' => $review,
        ]);
    }

    private function authorizeClientAccess(Contract $contract): void
    {
        $user = auth()->user();

        if ($user->is_master) {
            return;
        }

        if (! $contract->users()->where('users.id', $user->id)->exists()) {
            abort(403, 'Você não tem acesso a este contrato.');
        }
    }
}
