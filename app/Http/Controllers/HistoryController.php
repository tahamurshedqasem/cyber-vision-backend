<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\History;
use Illuminate\Support\Facades\Http;

class HistoryController extends Controller
{
    /**
     * Upload and analyze PCAP file using Flask AI API
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
            'threats' => 0,
            'confidence' => 0
        ]);

        try {

            // Send file to Flask API
            $response = Http::attach(
                'file',
                file_get_contents($file->getRealPath()),
                $filename
            )->post('https://cyber-vision-ai-production.up.railway.app/predict-pcap');

            if ($response->successful()) {

                $data = $response->json();

                // Read Flask response
                $prediction = $data['prediction'] ?? 'Unknown';

                $confidence = $data['confidence'] ?? 0;

                $features = $data['extracted_features'] ?? [];

                // Determine threats count
                $threats = ($prediction === "Attack") ? 1 : 0;

                // Update history
                $history->status = $prediction;
                $history->confidence = $confidence;
                $history->threats = $threats;

                // Save full report
                $history->report = json_encode([
                    'prediction' => $prediction,
                    'confidence' => $confidence,
                    'features' => $features
                ]);

                $history->save();

                return response()->json([
                    'message' => 'File analyzed successfully',
                    'prediction' => $prediction,
                    'confidence' => $confidence,
                    'features' => $features,
                    'history' => $history
                ]);
            }

            // Flask returned error
            $history->status = 'Error';
            $history->save();

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
     * Get all uploaded files for authenticated user
     */
    public function index(Request $request)
    {
        return History::where('user_id', $request->user()->id)
            ->latest()
            ->get();
    }

    /**
     * Get single history record
     */
    public function show($id, Request $request)
    {
        $history = History::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($history);
    }
}