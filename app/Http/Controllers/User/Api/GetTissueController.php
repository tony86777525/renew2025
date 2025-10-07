<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;
use App\Http\Requests\Api\Chocotissue\GetRecommendationTissuesRequest;
use App\Services\Chocotissue\RecommendationService;

class GetTissueController extends Controller
{
    private RecommendationService $recommendationService;
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(
        RecommendationService    $recommendationService
    ) {
        $this->recommendationService = $recommendationService;
    }

    public function getRecommendationTissues(GetRecommendationTissuesRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->recommendationService->getRecommendationData(
                $validated['is_pc'],
                $validated['load_times'],
                $validated['pref_id'] ?? null,
            );

            if ($data->isEmpty()) {
                return response()->json([
                    'html' => '',
                    'have_next_load' => false,
                ]);
            }

            $html = view('user.chocotissue.common.tissues', [
                'data' => $data,
            ])->render();

            return response()->json([
                'html' => $html,
                'have_next_load' => true,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('API: getRecommendationTissues error', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '系統暫時無法處理您的請求',
            ], 500);
        }
    }
}
