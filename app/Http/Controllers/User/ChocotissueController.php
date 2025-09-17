<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ChocotissueService;

class ChocotissueController extends Controller
{
    public function __construct(ChocotissueService $chocotissueService)
    {
        $this->chocotissueService = $chocotissueService;
    }

    public function index()
    {
        return view('user.chocotissue.index');
    }

    public function timeline(Request $request)
    {
        $data = $this->chocotissueService->getTimeline(true);
        // dd($data);
        return view('user.top.index');
    }

    public function recommendations(Request $request)
    {
        try {
            $data = $this->chocotissueService->getRecommendations(
                $request->boolean('is_pc', true),
                $request->integer('page', 1),
                $request->integer('pref_id', null)
            );

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
        // $data->each(function ($row) {
        //     echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        //     // echo "{$row->tissue->front_show_image_path}<BR>";
        // });
        // exit;
        return view('user.top.index');
    }

    public function userRankings()
    {
        $data = $this->chocotissueService->getUserRankings();
        // $data->each(function ($row) {
        //     echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        //     // echo "{$row->tissue->front_show_image_path}<BR>";
        // });
        // exit;
        return view('user.top.index');
    }

    public function shopRankings()
    {
        $displayedChocoShopTableIds = [];
        $displayedNightShopTableIds = [];

        $data = $this->chocotissueService->getShopRankings($displayedChocoShopTableIds, $displayedNightShopTableIds);

        // $data->each(function ($row) {
        //     echo "<BR><div>{$row->choco_shop_table_id}&{$row->night_shop_table_id}</div>";
        //     $row->tissues->each(function ($tissue) {
        //         echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$tissue->front_show_image_path}\">";
        //     });
        //     echo "<BR><BR><BR><BR><BR>";
        // });

        return view('user.top.index');
    }

    public function shopRankingDetail(Request $request)
    {
        $isTimeline = ($request->string('type', '')->toString() !== 'rank');

        // $chocoShopTableId = 20124;
        // $nightShopTableId = null;
        $chocoShopTableId = 7524;
        $nightShopTableId = 877;
        $page = 1;

        $data = $this->chocotissueService->getShopRankingDetail(
            $isTimeline,
            $chocoShopTableId,
            $nightShopTableId,
            $page,
        );

        return view('user.top.index');
    }

    public function hashtags(Request $request)
    {
        $displayedHashtagIds = [];

        $data = $this->chocotissueService->getHashtags($displayedHashtagIds);
//        dd($data);
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
            $page,
        );

        $data->each(function ($row) {
             echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
         });
        exit;
        return view('user.top.index');
    }

    public function likedItems()
    {
        return view('user.chocotissue.liked-items');
    }

    public function detail()
    {
        return view('user.chocotissue.detail');
    }
}
