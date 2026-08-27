<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class BackupController extends Controller
{
    public function index()
    {
        return view('admin.backup.index');
    }

    public function download()
    {
        $db       = config('database.connections.mysql');
        $host     = $db['host'];
        $port     = $db['port'] ?? 3306;
        $database = $db['database'];
        $username = $db['username'];
        $password = $db['password'];

        $filename = 'budgetra-backup-' . now()->format('Y-m-d-His') . '.sql';
        $dumpCmd  = "mysqldump --host={$host} --port={$port} --user={$username} --password={$password} {$database}";

        $result = Process::run($dumpCmd);

        if ($result->failed()) {
            return back()->with('error', 'Backup failed: ' . $result->errorOutput());
        }

        return response($result->output(), 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'sql_file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if ($value->getClientOriginalExtension() !== 'sql') {
                        $fail('Only .sql files are accepted.');
                    }
                },
            ],
        ]);

        $db       = config('database.connections.mysql');
        $host     = $db['host'];
        $port     = $db['port'] ?? 3306;
        $database = $db['database'];
        $username = $db['username'];
        $password = $db['password'];

        $sqlPath    = $request->file('sql_file')->getPathname();
        $restoreCmd = "mysql --host={$host} --port={$port} --user={$username} --password={$password} {$database} < {$sqlPath}";

        $result = Process::run($restoreCmd);

        if ($result->failed()) {
            return back()->with('error', 'Restore failed: ' . $result->errorOutput());
        }

        return back()->with('success', 'Database restored successfully.');
    }
}
