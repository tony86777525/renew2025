<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Exception;
use App\Services\Chocotissue\TimelineService;
use App\Services\Chocotissue\RecommendationService;
use App\Services\Chocotissue\UserWeeklyRankingService;
use App\Services\Chocotissue\UserRankingService;
use App\Services\Chocotissue\ShopRankingService;
use App\Services\Chocotissue\ShopRankingDetailService;
use App\Services\Chocotissue\HashtagService;
use App\Services\Chocotissue\HashtagDetailService;
use App\Services\Chocotissue\LikedService;
use App\Services\Chocotissue\DetailService;
use Jenssegers\Agent\Facades\Agent;

class ChocotissueController extends Controller
{
    private TimelineService $timelineService;
    private RecommendationService $recommendationService;
    private UserWeeklyRankingService $userWeeklyRankingService;
    private UserRankingService $userRankingService;
    private ShopRankingService $shopRankingService;
    private ShopRankingDetailService $shopRankingDetailService;
    private HashtagService $hashtagService;
    private HashtagDetailService $hashtagDetailService;
    private LikedService $likedService;
    private DetailService $detailService;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(
        TimelineService          $timelineService,
        RecommendationService    $recommendationService,
        UserWeeklyRankingService $userWeeklyRankingService,
        UserRankingService       $userRankingService,
        ShopRankingService       $shopRankingService,
        ShopRankingDetailService $shopRankingDetailService,
        HashtagService           $hashtagService,
        HashtagDetailService     $hashtagDetailService,
        LikedService             $likedService,
        DetailService            $detailService,
    ) {
        $this->timelineService = $timelineService;
        $this->recommendationService = $recommendationService;
        $this->userWeeklyRankingService = $userWeeklyRankingService;
        $this->userRankingService = $userRankingService;
        $this->shopRankingService = $shopRankingService;
        $this->shopRankingDetailService = $shopRankingDetailService;
        $this->hashtagService = $hashtagService;
        $this->hashtagDetailService = $hashtagDetailService;
        $this->likedService = $likedService;
        $this->detailService = $detailService;
    }

    /**
     * The Handle for liked tissue and recommendation.
     *
     * @param Request      $request
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
            $data = $this->timelineService->getTimelineData(1, $prefId);

            return view('user.chocotissue.timeline', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue timeline error', [
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
     * Recommendation
     *
     * @param Request      $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function recommendations(Request $request, ?int $prefId = null)
    {
        try {
            $data = $this->recommendationService->getRecommendationData(
                $request->get('is_pc', true),
                $request->get('page', 1),
                $prefId ?? null
            );

            return view('user.chocotissue.recommendations', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
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
     * User weekly ranking
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function userWeeklyRankings(Request $request, ?int $prefId = null)
    {
        try {
            $data = $this->userWeeklyRankingService->getUserWeeklyRankingData(1, $prefId);

            return view('user.chocotissue.user-ranking-weekly', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue user weekly rankings error', [
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
     * User ranking
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function userRankings(Request $request, ?int $prefId = null)
    {
        try {
            $data = $this->userRankingService->getUserRankingData(1, $prefId);

            return view('user.chocotissue.user-ranking', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue user ranking error', [
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
     * Shop ranking
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
            $data = $this->shopRankingService->getShopRankingData(
                $displayedChocoShopTableIds,
                $displayedNightShopTableIds,
                $page,
                $prefId
            );

            return view('user.chocotissue.shop-ranking', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue shop ranking error', [
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
     * Shop ranking detail
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
            list($shop, $casts) = $this->shopRankingDetailService->getShopRankingDetailData(
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue shop ranking detail error', [
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
     * Hashtag
     *
     * @param Request $request
     * @return View|object
     */
    public function hashtags(Request $request)
    {
        $displayedHashtagIds = [];

        try {
            $data = $this->hashtagService->getHashtagData($displayedHashtagIds);

            return view('user.chocotissue.hashtag', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue hashtag error', [
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
     * Hashtag detail
     *
     * @param Request $request
     * @return View|object
     */
    public function hashtagDetail(Request $request, $hashtagId)
    {
        $isTimeline = ($request->string('type', '')->toString() !== 'rank');
        $page = 1;

        try {
            $data = $this->hashtagDetailService->getHashtagDetailData(
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue hashtag detail error', [
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
     * Liked tissue
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
            $data = $this->likedService->getLikedData(
                $tissueIds,
                $page,
                $prefId
            );

            return view('user.chocotissue.liked-tissue', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue liked tissue error', [
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
            $data = $this->detailService->getDetailData($tissueId);

            return view('user.chocotissue.detail', compact(
                'data'
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            Log::error('Chocotissue detail error', [
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
