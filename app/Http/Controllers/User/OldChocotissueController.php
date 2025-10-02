<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Exception;
use App\Services\Old\ChocotissueService;

class OldChocotissueController extends Controller
{
    private ChocotissueService $chocotissueService;
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
     * Timeline
     *
     * @param Request $request
     * @param integer|null $prefId
     * @return View|object
     */
    public function timeline(Request $request, ?int $prefId = null)
    {

        $data = $this->chocotissueService->getCombinedTissues(null, true);

        return view('user.old.chocotissue.timeline', compact(
            'data'
        ));
//        dd($result);
//
//        try {
//            $data = $this->chocotissueService->getTimeline(1, $prefId);
//
//            return view('user.chocotissue.timeline', compact(
//                'data'
//            ));
//        } catch (InvalidArgumentException $e) {
//            // 參數錯誤 - 400
//            return response()->json([
//                'success' => false,
//                'message' => $e->getMessage(),
//            ], 400);
//        } catch (Exception $e) {
//            // 系統錯誤 - 500
//            Log::error('Chocotissue recommendation error', [
//                'request' => $request->all(),
//                'error' => $e->getMessage(),
//                'trace' => $e->getTraceAsString()
//            ]);
//
//            return response()->json([
//                'success' => false,
//                'message' => '系統暫時無法處理您的請求',
//            ], 500);
//        }
    }
}
