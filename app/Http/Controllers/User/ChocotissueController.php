<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Exception;
use App\Services\ChocotissueService;

class ChocotissueController extends Controller
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(ChocotissueService $chocotissueService)
    {
        $this->chocotissueService = $chocotissueService;
    }

    /**
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function handle(Request $request, ?int $prefId = null)
    {
        if ($request->get('sort') == 3) {
            return self::likedTissues($request, $prefId);
        }

        return self::recommendations($request, $prefId);
    }

    /**
     * Timeline
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function timeline(Request $request, ?int $prefId = null)
    {
        try {
            $data = $this->chocotissueService->getTimeline(1, $prefId);

            return view('user.chocotissue.timeline', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * Recommendations
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function recommendations(Request $request, ?int $prefId = null)
    {
        try {
            $data = $this->chocotissueService->getRecommendations(
                $request->get('is_pc', true),
                $request->get('page', 1),
                $prefId ?? null
            );

            return view('user.chocotissue.recommendations', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * UserWeeklyRankings
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function userWeeklyRankings(Request $request, ?int $prefId = null)
    {
        try {
            $data = $this->chocotissueService->getUserWeeklyRankings(1, $prefId);

            return view('user.chocotissue.user-ranking-weekly', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * UserRankings
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function userRankings(Request $request, ?int $prefId = null)
    {
        try {
            $data = $this->chocotissueService->getUserRankings(1, $prefId);

            return view('user.chocotissue.user-ranking', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * ShopRankings
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function shopRankings(Request $request, ?int $prefId = null)
    {
        $displayedChocoShopTableIds = [];
        $displayedNightShopTableIds = [];
        $page = 1;

        try {
            $data = $this->chocotissueService->getShopRankings(
                $displayedChocoShopTableIds,
                $displayedNightShopTableIds,
                $page,
                $prefId
            );

            return view('user.chocotissue.shop-ranking', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * ShopRankingDetail
     *
     * @param Request $request
     * @return View|object
     */
    public function shopRankingDetail(Request $request)
    {
        $isTimeline = ($request->string('type', '')->toString() !== 'rank');

        // $chocoShopTableId = 20124;
        // $nightShopTableId = null;
        $chocoShopTableId = 23130;
        $nightShopTableId = 8588;
        $page = 1;

        try {
            list($shop, $casts) = $this->chocotissueService->getShopRankingDetail(
                $isTimeline,
                $chocoShopTableId,
                $nightShopTableId,
                $page
            );

            if ($isTimeline) {
                return view('user.chocotissue.shop-ranking-timeline', compact(
                    'shop',
                    'casts'
                ));
            } else {
                return view('user.chocotissue.shop-ranking-ranking', compact(
                    'shop',
                    'casts'
                ));
            }
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * Hashtags
     *
     * @param Request $request
     * @return View|object
     */
    public function hashtags(Request $request)
    {
        $displayedHashtagIds = [];

        try {
            $data = $this->chocotissueService->getHashtags($displayedHashtagIds);

            return view('user.chocotissue.hashtag', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * Hashtag Detail
     *
     * @param Request $request
     * @return View|object
     */
    public function hashtagDetail(Request $request, $hashtagId)
    {
        $isTimeline = ($request->string('type', '')->toString() !== 'rank');
        $page = 1;

        try {
            $data = $this->chocotissueService->getHashtagDetail(
                $isTimeline,
                $hashtagId,
                $page
            );

            if ($isTimeline) {
                return view('user.chocotissue.hashtag-timeline', compact(
                    'data'
                ));
            } else {
                return view('user.chocotissue.hashtag-ranking', compact(
                    'data'
                ));
            }
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * Liked Tissue
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function likedTissues(Request $request, ?int $prefId = null)
    {
        $tissueIds = [30423];
        $page = 1;

        try {
            $data = $this->chocotissueService->getTissues(
                $tissueIds,
                $page,
                $prefId
            );

            return view('user.chocotissue.liked-tissue', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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

    /**
     * Detail
     *
     * @param Request $request
     * @return View|object
     */
    public function detail(Request $request, $tissueId)
    {
        try {
            $data = $this->chocotissueService->getTissues(
                [$tissueId]
            );

            $data = $data->first();

            return view('user.chocotissue.detail', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            // 系統錯誤 - 500
            Log::error('Chocotissue recommendation error', [
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
