<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcapController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $data = $request->attributes->get('analysisData');

        if ($data['status'] === 'processing') {
            return response()->json([
                'status' => 'processing',
                'message' => 'Still analyzing, try refreshing in a few seconds.',
            ]);
        }

        return response()->json($data);
    }
}