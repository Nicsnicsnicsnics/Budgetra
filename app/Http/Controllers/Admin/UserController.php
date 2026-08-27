<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /**
     * Sortable columns and the direction each one opens in. All three read
     * "most first" by default — more trips, bigger spend — which is what an
     * admin scanning the list is looking for.
     */
    private const SORT_DEFAULTS = [
        'trips'   => 'desc',
        'total'   => 'desc',
        'average' => 'desc',
    ];

    /**
     * Raw ORDER BY expressions. Postgres allows ordering by a select alias but
     * not by an expression built from two of them, so "average" repeats the
     * subqueries rather than dividing withSum's alias by withCount's.
     * NULLIF keeps a user with no trips from dividing by zero.
     */
    private const SORT_EXPRESSIONS = [
        'trips'   => '(SELECT COUNT(*) FROM trips WHERE trips.user_id = users.id)',
        'total'   => '(SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expenses.user_id = users.id)',
        'average' => '(SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expenses.user_id = users.id)
                      / NULLIF((SELECT COUNT(*) FROM trips WHERE trips.user_id = users.id), 0)',
    ];

    private function filteredQuery(Request $request)
    {
        // Admin accounts aren't travelers and aren't moderated from here, so
        // they're excluded from the list (and from the CSV export, which runs
        // through this same query). orWhereNull keeps any role-less row: a
        // bare "!= 'admin'" would silently drop those too, since NULL != 'admin'
        // is unknown rather than true.
        $query = User::withCount('trips')->withSum('expenses', 'amount')
            ->where(fn ($q) => $q->where('role', '!=', 'admin')->orWhereNull('role'));
        if ($request->filled('search')) {
            $s = '%' . strtolower($request->search) . '%';
            $query->where(fn($q) => $q->whereRaw('LOWER(full_name) LIKE ?', [$s])->orWhereRaw('LOWER(email) LIKE ?', [$s]));
        }
        return $query;
    }

    /** Applies a whitelisted sort, falling back to newest-first. */
    private function applySort($query, ?string $sort, string $dir)
    {
        if (!$sort) {
            return $query->latest();
        }

        // NULLS LAST so users with no spending sink to the bottom on a
        // descending sort instead of floating above everyone.
        $direction = $dir === 'asc' ? 'ASC' : 'DESC';
        $nulls     = $dir === 'asc' ? 'NULLS FIRST' : 'NULLS LAST';

        return $query
            ->orderByRaw(self::SORT_EXPRESSIONS[$sort] . " {$direction} {$nulls}")
            ->orderBy('id');   // stable tiebreak
    }

    public function index(Request $request)
    {
        $active = 'users';

        $sort = $request->query('sort');
        $sort = array_key_exists($sort, self::SORT_DEFAULTS) ? $sort : null;

        $dir = $request->query('dir');
        $dir = in_array($dir, ['asc', 'desc'], true)
            ? $dir
            : ($sort ? self::SORT_DEFAULTS[$sort] : 'desc');

        $users = $this->applySort($this->filteredQuery($request), $sort, $dir)
            ->paginate(25)->withQueryString();

        // Counts the same population the table lists: every non-admin
        // account, banned ones included, since the list no longer filters
        // or flags them separately.
        $totalUsers   = User::where(fn ($q) => $q->where('role', '!=', 'admin')->orWhereNull('role'))->count();
        $bannedUsers  = User::where('role', 'banned')->count();
        $tripsThisMonth = Trip::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return view('admin.users.index', compact(
            'users', 'active', 'totalUsers', 'bannedUsers', 'tripsThisMonth', 'sort', 'dir'
        ) + ['sortDefaults' => self::SORT_DEFAULTS]);
    }

    public function export(Request $request): StreamedResponse
    {
        $users = $this->filteredQuery($request)->latest()->get();

        $filename = 'users-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($users) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Role', 'Trips', 'Registered']);
            foreach ($users as $user) {
                fputcsv($out, [
                    $user->full_name,
                    $user->email,
                    $user->role,
                    $user->trips_count,
                    $user->created_at->format('Y-m-d'),
                ]);
            }
            fclose($out);
        }, $filename, $headers);
    }

    public function show(User $user)
    {
        $active = 'users';
        $user->load(['trips', 'reviews', 'expenses']);
        return view('admin.users.show', compact('user', 'active'));
    }

    public function ban(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot ban yourself.');
        $user->update(['role' => $user->role === 'banned' ? 'traveler' : 'banned']);
        $action = $user->role === 'banned' ? 'banned' : 'unbanned';
        return back()->with('success', "User {$action}.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
