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
        $data = $this->service->getUserWeeklyRankings();
        // $data->each(function ($row) {
        //     echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        //     // echo "{$row->tissue->front_show_image_path}<BR>";
        // });
        // exit;
        return view('user.top.index');
    }

    public function userRankings()
    {
        $data = $this->service->getUserRankings();
        // $data->each(function ($row) {
        //     echo "<img style=\"width: 18vw;max-height:180px;\" src=\"{$row->tissue->front_show_image_path}\">";
        //     // echo "{$row->tissue->front_show_image_path}<BR>";
        // });
        // exit;
        return view('user.top.index');
    }

    public function shopRankings()
    {
        $data = $this->service->getShopRankings();
        // dd($data);
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
