<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

    public function handle(Request $request)
    {
        if ($request->get('sort') == 3) {
            return self::likedTissues($request);
        }

        return self::recommendations($request);
    }

    /**
     * Timeline
     *
     * @return void
     */
    public function timeline(Request $request)
    {
        $data = $this->chocotissueService->getTimeline(1);

        $data->each(function ($row) {
            echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        });
        exit;
        return view('user.top.index');
    }

    /**
     * Recommendations
     *
     * @return void
     */
    public function recommendations(Request $request)
    {
        try {
            $data = $this->chocotissueService->getRecommendations(
                $request->get('is_pc', true),
                $request->get('page', 1),
                $request->get('pref_id', null)
            );

            $data->each(function ($row) {
                echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
            });
            exit;
            return view('user.chocotissue.recommendations');
        } catch (\InvalidArgumentException $e) {
            // 參數錯誤 - 400
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            // 系統錯誤 - 500
            \Log::error('Chocotissue recommendation error', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '系統暫時無法處理您的請求'
            ], 500);
        }
    }

    public function userWeeklyRankings()
    {
        $data = $this->chocotissueService->getUserWeeklyRankings();
        $data->each(function ($row) {
            echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
            // echo "{$row->tissue->front_show_image_path}<BR>";
        });
        exit;
        return view('user.top.index');
    }

    public function userRankings()
    {
        $data = $this->chocotissueService->getUserRankings();
        $data->each(function ($row) {
            echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        });
        exit;
        return view('user.top.index');
    }

    public function shopRankings()
    {
        $displayedChocoShopTableIds = [];
        $displayedNightShopTableIds = [];
        $page = 1;
        $prefId = null;

        $data = $this->chocotissueService->getShopRankings($displayedChocoShopTableIds, $displayedNightShopTableIds, $page, $prefId);

        $data->each(function ($row) {
            echo "<BR><div>{$row->choco_shop_table_id} | $row->choco_shop_pref_id & {$row->night_shop_table_id} | $row->night_shop_pref_id </div>";
            echo "<div>Casts：{$row->cast_ids} </div><BR>";
            $row->tissues->each(function ($tissue) {
                echo "<div style=\"display: inline-flex;flex-direction: row;align-items: flex-start;flex-wrap: wrap;position: relative;\">
<div style=\"width: 18vw;\">
<div>User: {$tissue->user_type}</div>
<div>User ID: <span style=\"color: red;\">{$tissue->user_id}</span></div>
<div>Top Tissue ID: <span style=\"color: red;\">{$tissue->id}</span></div>
<img style=\"max-height:262px;max-width:200px;\" src=\"{$tissue->front_show_image_path}\">
</div>
</div>";
            });
            echo "<BR><BR><BR><BR><BR>";
        });
        exit;
        return view('user.top.index');
    }

    public function shopRankingDetail(Request $request)
    {
        $isTimeline = ($request->string('type', '')->toString() !== 'rank');

        // $chocoShopTableId = 20124;
        // $nightShopTableId = null;
        $chocoShopTableId = 23130;
        $nightShopTableId = 8588;
        $page = 1;

        list($shop, $casts) = $this->chocotissueService->getShopRankingDetail(
            $isTimeline,
            $chocoShopTableId,
            $nightShopTableId,
            $page
        );

        if ($isTimeline) {
            $casts->each(function ($row) {
                echo "<div style=\"display: inline-flex;flex-direction: row;align-items: flex-start;flex-wrap: wrap;position: relative;\">
<div style=\"width: 18vw;\">
<div>User: {$row->tissue->user_type}</div>
<div>User ID: <span style=\"color: red;\">{$row->tissue->user_id}</span></div>
<div>Top Tissue ID: <span style=\"color: red;\">{$row->tissue->id}</span></div>
<div>Last Comment datetime: <span style=\"color: red;\">{$row->last_comment_datetime}</span></div>
<div>Release datetime: <span style=\"color: red;\">{$row->release_date}</span></div>
<img style=\"max-height:262px;max-width:200px;\" src=\"{$row->tissue->front_show_image_path}\">
</div>
</div>";
            });
        } else {
            $casts->each(function ($row) {
                echo "<div style=\"display: inline-flex;flex-direction: row;align-items: flex-start;flex-wrap: wrap;position: relative;\">
<div style=\"width: 18vw;\">
<div>User: {$row->tissue->user_type}</div>
<div>User ID: <span style=\"color: red;\">{$row->tissue->user_id}</span></div>
<div>Top Tissue ID: <span style=\"color: red;\">{$row->tissue->id}</span></div>
<div>Point: <span style=\"color: red;\">{$row->rank_point}</span></div>
<img style=\"max-height:262px;max-width:200px;\" src=\"{$row->tissue->front_show_image_path}\">
</div>
</div>";
            });
        }

        exit;
        return view('user.top.index');
    }

    public function hashtags(Request $request)
    {
        $displayedHashtagIds = [];

        $data = $this->chocotissueService->getHashtags($displayedHashtagIds);

        $data->each(function ($row) {
            echo "<div>{$row->hashtag->id} : {$row->hashtag->name} : {$row->total_view_count}</div>";
            $row->tissues->each(function ($tissue) {
                echo "<img style=\"width: 10vw;max-height:180px;\" src=\"{$tissue->front_show_image_path}\">";
            });
            echo "<BR><BR><BR><BR><BR>";
        });
        exit;
        return view('user.top.index');
    }

    public function hashtagDetail(Request $request, $hashtagId)
    {
        $isTimeline = ($request->string('type', '')->toString() !== 'rank');
        $page = 1;

        $data = $this->chocotissueService->getHashtagDetail(
            $isTimeline,
            $hashtagId,
            $page
        );

        $data->each(function ($row) {
            echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        });
        exit;
        return view('user.top.index');
    }

    public function likedTissues()
    {
        $tissueIds = [30423];
        $page = 1;

        $data = $this->chocotissueService->getTissues(
            $tissueIds,
            $page,
        );

        $data->each(function ($row) {
            echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        });
        exit;
        return view('user.top.index');
    }

    public function detail($tissueId)
    {
        $data = $this->chocotissueService->getTissues(
            [$tissueId]
        );

        $data = $data->first();

        echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$data->tissue->front_show_image_path}\">";
        exit;
        return view('user.chocotissue.detail');
    }
}
