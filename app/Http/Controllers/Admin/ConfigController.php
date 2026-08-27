<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use App\Models\OcrLog;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function index()
    {
        $configs = AppConfig::pluck('config_value', 'config_key');
        return view('admin.config.index', compact('configs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'config_key'   => 'required|string|max:100',
            'config_value' => 'nullable|string|max:1000',
        ]);

        AppConfig::updateOrCreate(
            ['config_key'   => $request->config_key],
            ['config_value' => $request->config_value]
        );

        return back()->with('success', 'Configuration saved.');
    }

    public function ocrLogs()
    {
        $logs = OcrLog::with('user')->latest()->paginate(50);
        return view('admin.ocr.index', compact('logs'));
    }
}
