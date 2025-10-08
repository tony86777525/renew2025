<?php

namespace App\Http\Controllers\User\Api;

use App\Http\Controllers\Controller;
use App\Services\Chocotissue\ShopRankingService;
use App\Services\Chocotissue\UserWeeklyRankingService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Exception;
use App\Http\Requests\Api\Chocotissue\GetRecommendationTissuesRequest;
use App\Http\Requests\Api\Chocotissue\GetUserRankingTissuesRequest;
use App\Http\Requests\Api\Chocotissue\GetUserRankingWeeklyTissuesRequest;
use App\Http\Requests\Api\Chocotissue\GetShopRankingTissuesRequest;
use App\Http\Requests\Api\Chocotissue\GetLikedTissuesRequest;
use App\Services\Chocotissue\RecommendationService;
use App\Services\Chocotissue\UserRankingService;
use App\Services\Chocotissue\LikedService;

class GetTissueController extends Controller
{
    private RecommendationService $recommendationService;
    private UserRankingService $userRankingService;
    private UserWeeklyRankingService $userWeeklyRankingService;
    private ShopRankingService $shopRankingService;
    private LikedService $likedService;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(
        RecommendationService    $recommendationService,
        UserRankingService       $userRankingService,
        UserWeeklyRankingService $userWeeklyRankingService,
        ShopRankingService       $shopRankingService,
        LikedService             $likedService
    ) {
        $this->recommendationService = $recommendationService;
        $this->userRankingService =  $userRankingService;
        $this->userWeeklyRankingService = $userWeeklyRankingService;
        $this->shopRankingService = $shopRankingService;
        $this->likedService = $likedService;
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

    public function getUserRankingTissues(GetUserRankingTissuesRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->userRankingService->getUserRankingData(
                $validated['load_times'],
                $validated['pref_id'] ?? null,
            );

            if ($data->isEmpty()) {
                return response()->json([
                    'html' => '',
                    'have_next_load' => false,
                ]);
            }

            $html = view('user.chocotissue.common.user-ranking-tissues', [
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
            Log::error('API: getUserRankingTissues error', [
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

    public function getUserRankingWeeklyTissues(GetUserRankingWeeklyTissuesRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->userWeeklyRankingService->getUserWeeklyRankingData(
                $validated['load_times'],
                $validated['pref_id'] ?? null,
            );

            if ($data->isEmpty()) {
                return response()->json([
                    'html' => '',
                    'have_next_load' => false,
                ]);
            }

            $html = view('user.chocotissue.common.user-ranking-weekly-tissues', [
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
            Log::error('API: getUserRankingWeeklyTissues error', [
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

    public function getShopRankingTissues(GetShopRankingTissuesRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->shopRankingService->getShopRankingData(
                $validated['displayed_choco_shop_table_ids'] ?? [],
                $validated['displayed_night_shop_table_ids'] ?? [],
                $validated['load_times'],
                $validated['pref_id'] ?? null,
            );

            if ($data->isEmpty()) {
                return response()->json([
                    'html' => '',
                    'have_next_load' => false,
                ]);
            }

            $html = view('user.chocotissue.common.shop-ranking-tissues', [
                'data' => $data,
            ])->render();

            $displayedChocoShopTableIds = $data
                ->filter(function ($row) {
                    return $row->choco_shop_table_id !== null;
                })
                ->pluck('choco_shop_table_id')
                ->values()
                ->toArray();

            $displayedNightShopTableIds = $data
                ->filter(function ($row) {
                    return $row->choco_shop_table_id === null && $row->night_shop_table_id !== null;
                })
                ->pluck('night_shop_table_id')
                ->values()
                ->toArray();

            $newDisplayedChocoShopTableIds = array_merge($validated['displayed_choco_shop_table_ids'] ?? [], $displayedChocoShopTableIds);
            $newDisplayedNightShopTableIds = array_merge($validated['displayed_night_shop_table_ids'] ?? [], $displayedNightShopTableIds);

            return response()->json([
                'html' => $html,
                'have_next_load' => true,
                'new_displayed_choco_shop_table_ids' => $newDisplayedChocoShopTableIds,
                'new_displayed_night_shop_table_ids' => $newDisplayedNightShopTableIds,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('API: getShopRankingTissues error', [
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

    public function getLikedTissues(GetLikedTissuesRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->likedService->getLikedData(
                $validated['tissue_ids'],
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
            Log::error('API: getLikedTissues error', [
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
