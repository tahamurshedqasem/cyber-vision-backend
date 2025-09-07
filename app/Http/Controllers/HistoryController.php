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

        // إضافة سجل مبدئي في جدول history
        $history = History::create([
            'user_id' => $request->user()->id,
            'file_name' => $filename,
            'status' => 'Pending',
            'threats' => 0
        ]);

        // إرسال الملف إلى Flask API
        try {
            $response = Http::attach(
                'file',
                file_get_contents($file->getRealPath()),
                $filename
            )->post('http://127.0.0.1:5000/api/upload'); // <-- Flask endpoint

            if ($response->successful()) {
                $data = $response->json();
                $analysis = $data['analysis'];

                // تحديث السجل بالنتائج
                $history->status = $analysis['status'];
                $history->threats =
                    count($analysis['anomalies']) +
                    count($analysis['port_scanners']) +
                    count($analysis['brute_force']);
                $history->report = $analysis;
                $history->save();

                return response()->json([
                    'message' => 'File analyzed successfully',
                    'history' => $history
                ]);
            } else {
                return response()->json([
                    'message' => 'Error from Flask API',
                    'error' => $response->body()
                ], 500);
            }
        } catch (\Exception $e) {
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
