<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\History;
use Illuminate\Support\Facades\Http;

class HistoryController extends Controller
{
    /**
     * رفع ملف PCAP وتحليله عبر Flask API
     */
    public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:pcap'
    ]);

    $file = $request->file('file');
    $filename = time() . '_' . $file->getClientOriginalName();
    $path = $file->storeAs('pcaps', $filename, 'public');

    // Create initial history record
    $history = History::create([
        'user_id' => $request->user()->id,
        'file_name' => $filename,
        'status' => 'Processing',
        'threats' => 0
    ]);

    try {

        // Send file to Flask API
        $response = Http::attach(
            'file',
            file_get_contents($file->getRealPath()),
            $filename
        )->post('https://cyber-vision-ai-production.up.railway.app/predict-pcap'); // ✅ Updated endpoint

      if ($response->successful()) {

    $data = $response->json();

    $prediction = $data['final_decision'] ?? 'Unknown';

    $confidence = max([
        $data['models_results']['svm']['confidence'] ?? 0,
        $data['models_results']['xgb']['confidence'] ?? 0
    ]);

    // Update history
    $history->status = $prediction;
    $history->confidence = $confidence;
    $history->report = json_encode($data);
    $history->threats = ($prediction === "Attack") ? 1 : 0;
    $history->save();

    return response()->json([
        'message' => 'File analyzed successfully',
        'prediction' => $prediction,
        'confidence' => $confidence,
        'history' => $history
    ]);
}
        return response()->json([
            'message' => 'Error from Flask API',
            'error' => $response->body()
        ], 500);

    } catch (\Exception $e) {

        $history->status = 'Error';
        $history->save();

        return response()->json([
            'message' => 'Flask API unreachable',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * جلب كل الملفات التي رفعها المستخدم
     */
    public function index(Request $request)
    {
        return History::where('user_id', $request->user()->id)
            ->latest()
            ->get();
    }

    /**
     * جلب ملف محدد بالتفاصيل
     */
    public function show($id, Request $request)
    {
        $history = History::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($history);
    }
}
