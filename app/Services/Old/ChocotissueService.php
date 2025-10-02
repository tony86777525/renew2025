<?php

namespace App\Services\Old;

use App\Models\Chocolat\Cast;
use App\Models\Chocolat\ShopMain;
use App\Models\Chocolat\Tissue;
use App\Models\Chocolat\TissueActiveView;
use App\Models\Chocolat\TissueComment;
use App\Models\Chocolat\RankingPoint;
use App\Models\Chocolat\TissueNightOsusumeActiveView;
use App\Models\Chocolat\WeeklyRankingPoint;
use App\Models\Chocolat\AreaPref;
use App\Models\Night\Cast AS NightCast;
use App\Models\Night\Member;
use App\Models\Night\Shop AS NightShop;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Facades\Agent;

class ChocotissueService
{
    const PERSONAL_RANKING_SORT = '2';

    CONST THUMBNAIL_SECOND = '-00003.png';
    CONST CookieName = 'tissue';
    CONST mensCookieName = 'mens_tissue';

    const TISSUE_TYPE_GIRL = 'GIRL';
    const TISSUE_TYPE_GIRL_CAST = 'GIRL_CAST';
    const TISSUE_TYPE_GIRL_SHOP = 'GIRL_SHOP';
    const TISSUE_TYPE_GIRL_GUEST = 'GIRL_GUEST';
    const TISSUE_TYPE_GIRL_MYPAGE = 'GIRL_MYPAGE';
    const TISSUE_TYPE_GIRL_NIGHT_SHOP = 'GIRL_NIGHT_SHOP';
    const TISSUE_TYPE_GIRL_NIGHT_CAST = 'GIRL_NIGHT_CAST';

    const TISSUE_TYPE_MEN = 'MEN';
    const TISSUE_TYPE_MEN_STAFF = 'MEN_STAFF';
    const TISSUE_TYPE_MEN_SHOP = 'MEN_SHOP';
    const TISSUE_TYPE_MEN_GUEST = 'MEN_GUEST';
    const TISSUE_TYPE_MEN_MYPAGE = 'MEN_MYPAGE';
    const TISSUE_TYPE_MEN_NIGHT_SHOP = 'MEN_NIGHT_SHOP'; // 全権 can create this type of tissue
    const TISSUE_TYPE_MEN_NIGHT_MYPAGE = 'MEN_NIGHT_MYPAGE';

    private $isBindCast = false;

    CONST FLITER_CAST_ID = [
        // 第一回
        '14377',
        // 第二回
        '16624',
        // 第三回
        '14451',
        // 第四回
        '13455',
    ];

    /**
     * Get mens tissue and tissue all combined
     *
     *  @return array
     */
    public function getCombinedTissues(
        ?int $pref = null,
        bool $pagenation = false,
        int $limit = 0,
        int $currentPage = 0,
        bool $slider = false,
        bool $new_movie = false,
        bool $isOsusumeOrder = false
    ) {
        // Determine table name and model based on condition
        $tableName = $isOsusumeOrder ? 'tissue_night_osusume_task_active_view' : 'tissue_active_view';
        $model = $isOsusumeOrder ? TissueNightOsusumeActiveView::class : TissueActiveView::class;

        // Define the raw select columns (common for both cases)
        $selectColsRaw = "$tableName.*, $tableName.target_id as tid,
                        ($tableName.good_count + $tableName.add_good_count) AS total_good_count";

        // Execute the query using the model and raw select columns
        $result = $model::select(DB::RAW($selectColsRaw));

        $result = $result
            ->with([
                'nightCastShopMains',
                'shopMains',
            ])
            ->where(function ($query) use ($slider, $new_movie, $tableName) {
                if ($slider) {
                    $query->where("$tableName.slider_num", '!=', null)
                        ->orWhere("$tableName.tissue_type", '=' , 'GIRL');
                } elseif (true === $new_movie) {
                    $query->where("$tableName.movie_url", '<>', '')
                        ->where("$tableName.created_at", '<', Carbon::now()->subMinutes(1)->toDateTimeString())
                        ->where("$tableName.release_date", '>=', Carbon::now()->subDay()->toDateTimeString());
                } else {
                    $query->where("$tableName.image_url", '<>', '')
                        ->orWhere(function ($query) use ($tableName){
                            $query->where("$tableName.movie_url", '<>', '')->where("$tableName.created_at", '<',
                                Carbon::now()->subMinutes(1)->toDateTimeString());
                        });
                }
            })->where("$tableName.release_date", '<=', Carbon::now());

        if (!empty($pref)) {
            $result = $result->where("$tableName.pref_id", '=', $pref);
        }

        if (!$slider) {
            $result = $result->where("$tableName.release_date", '>=', new Carbon('2025-04-01'));
        }

        // slider sort
        if ($slider) {
            $result = $result->orderByRaw("-$tableName.slider_num DESC , $tableName.release_date DESC");
        } else {
            if ($isOsusumeOrder === true) {
                $media = (Agent::isMobile() === true) ? 'sp' : 'pc';
                $result = $result->join(
                    "tissue_night_recommend_{$media}", function ($join) use($media, $tableName) {
                    $join->on("$tableName.tissue_id", '=', "tissue_night_recommend_{$media}.tissue_id")
                        ->on("$tableName.tissue_type", '=', "tissue_night_recommend_{$media}.tissue_type");
                }
                );
                $orderBy = ["tissue_night_recommend_{$media}.id ASC"];
            } else {
                // promotion order
                $orderBy = ["$tableName.release_date DESC", "$tableName.tissue_id DESC"];
            }

            $orderByRaw = implode(',', $orderBy);

            $result = $result->orderByRaw($orderByRaw);
        }

        $result = $result->published()->tissueStatusActive()->limit(30)->get();

        // Ranking Row
        $rankingRows = collect();
        if (!empty($pref)) {
            $rankingRows = $this->getEachPrefRanking(array($pref));
        } else {
            $rankingRows = $this->getEachPrefRanking($this->existsPref()->pluck('area_id'));
        }

        // Get Cookie
        $cookies[self::TISSUE_TYPE_GIRL] = json_decode(Cookie::get(self::CookieName), true);
        $cookies[self::TISSUE_TYPE_MEN] = json_decode(Cookie::get(self::mensCookieName), true);

        $nightTownShopIds = [];
        $tissueIds = [];
        $menTissueIds = [];

        foreach ($result as $v) {
            $v->id = $v->tissue_id;

            if ($v->tissue_type == self::TISSUE_TYPE_GIRL) {
                if ($v->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_CAST) {
                    $nightTownShopIds[] = $v->nightCastShopMains->id;
                } elseif ($v->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_SHOP) {
                    $nightTownShopIds[] = $v->target_id;
                } elseif ($v->tissue_from_type == self::TISSUE_TYPE_GIRL_CAST) {
                    $nightTownShopIds[] = $v->castShopMains->night_town_id;
                } elseif ($v->tissue_from_type == self::TISSUE_TYPE_GIRL_SHOP) {
                    $nightTownShopIds[] = $v->shopMains->night_town_id;
                }
            }

            if ($v->tissue_type == self::TISSUE_TYPE_GIRL) {
                $tissueIds[] = $v->id;
            } else {
                $menTissueIds[] = $v->id;
            }
        }

        // Get night shops related to these tissues ($result)
        $nightShops = self::getNightTownShopByNightShopIds($nightTownShopIds);

        // Get night casts of night cast tissues
        $nightCastIds = $result->where('tissue_from_type', self::TISSUE_TYPE_GIRL_NIGHT_CAST)
            ->pluck('target_id')->filter();
        $nightCasts = self::getNightTownCastByCastIds($nightCastIds);

        $tissueCommentCountArr[self::TISSUE_TYPE_MEN] = 0;
        $tissueCommentCountArr[self::TISSUE_TYPE_GIRL] = $this->getTissueCommentMaster($tissueIds, [], 0, 0, 0, true, true);

        $member = $this->getMemberFromCookie();

        $result = $result->each(function ($r) use ($rankingRows, $cookies, $nightShops, $nightCasts, $tissueCommentCountArr, $member) {
            $this->attachDetailAttributesCombinedTissue($member,$r, $rankingRows, $cookies, $nightShops, $nightCasts, $tissueCommentCountArr);
        });

        return $result;
    }

    /**
     * get list from tissue
     * @param integer|null $pref
     * @param string|null  $sort
     * @param boolean      $pagenation
     * @param integer      $limit
     * @param boolean      $random
     * @param boolean      $onlyCastAndGuest
     * @param integer      $currentPage
     * @param integer      $startRollingPage
     * @param integer      $pay_flg
     * @param boolean      $slider
     * @param boolean      $new_movie
     * @param string|null  $releaseDateLimit
     * @param boolean      $isChampionship
     * @param boolean      $isLikeSortList If true, get tissues for person ranking page
     * @param integer|null $hashTagId
     * @param boolean      $isOsusumeOrder
     * @param bool $isPersonalWeeklyList
     * @param bool $isPersonalRankingOrder
     * @param bool $isWeeklyReleaseDate
     * @param bool $isBeforeWeeklyReleaseDate
     * @return array
     * @throws \Exception
     */
    public function getList(
        ?int $pref = null,
        ?string $sort = null,
        bool $pagenation = false,
        int $limit = 0,
        bool $random = false,
        bool $onlyCastAndGuest = false,
        int $currentPage = 0,
        int $startRollingPage = 0,
        int $pay_flg = 5,
        bool $slider = false,
        bool $new_movie = false,
        ?string $releaseDateLimit = null,
        bool $isChampionship = false,
        bool $isLikeSortList = false,
        ?int $hashTagId = null,
        bool $isOsusumeOrder = false,
        bool $isPersonalWeeklyList = false,
        bool $isPersonalRankingOrder = false,
        bool $isWeeklyReleaseDate = false,
        bool $isBeforeWeeklyReleaseDate = false
    ) {
        $weekStartDate = new Carbon('monday this week');
        $snsWeekStartDate = (new Carbon('monday this week'))->subDays(2);
        $lastWeekStartDate = (new Carbon('monday this week'))->subWeek();

        if (true === $isLikeSortList) {
            if (!empty($releaseDateLimit)) {
                $image_sub_query = '(select CASE
                WHEN image_url != \'\' THEN CONCAT(ts.id, \'-\',case
                WHEN old_mypage_id IS NOT NULL THEN CONCAT(\'oldMypage-image-\', old_mypage_id, \'-\')
                WHEN old_cast_id IS NOT NULL THEN CONCAT(\'oldCast-image-\', old_cast_id, \'-\')
                WHEN old_night_cast_id IS NOT NULL THEN CONCAT(\'oldNightCast-image-\', old_night_cast_id, \'-\')
                WHEN cast_id IS NOT NULL THEN \'cast-image-\'
                WHEN guest_id IS NOT NULL THEN \'guest-image-\'
                WHEN night_cast_id IS NOT NULL THEN \'nightCast-image-\'
                ELSE \'mypage-image-\' END, image_url)
                WHEN thumbnail_url != \'\' THEN CONCAT(ts.id, \'-\',case
                WHEN old_mypage_id IS NOT NULL THEN CONCAT(\'oldMypage-thumbnail-\', old_mypage_id, \'-\')
                WHEN old_cast_id IS NOT NULL THEN CONCAT(\'oldCast-thumbnail-\', old_cast_id, \'-\')
                WHEN old_night_cast_id IS NOT NULL THEN CONCAT(\'oldNightCast-thumbnail-\', old_night_cast_id, \'-\')
                WHEN cast_id IS NOT NULL THEN \'cast-thumbnail-\'
                WHEN guest_id IS NOT NULL THEN \'guest-thumbnail-\'
                WHEN night_cast_id IS NOT NULL THEN \'nightCast-thumbnail-\'
                ELSE \'mypage-thumbnail-\' END, thumbnail_url)
                WHEN movie_url != \'\' THEN CONCAT(ts.id, \'-\',case
                WHEN old_mypage_id IS NOT NULL THEN CONCAT(\'oldMypage-movie-\', old_mypage_id, \'-\')
                WHEN old_cast_id IS NOT NULL THEN CONCAT(\'oldCast-movie-\', old_cast_id, \'-\')
                WHEN old_night_cast_id IS NOT NULL THEN CONCAT(\'oldNightCast-movie-\', old_night_cast_id, \'-\')
                WHEN cast_id IS NOT NULL THEN \'cast-movie-\'
                WHEN guest_id IS NOT NULL THEN \'guest-movie-\'
                WHEN night_cast_id IS NOT NULL THEN \'nightCast-movie-\'
                ELSE \'mypage-movie-\' END, movie_url) ELSE NULL END
                FROM tissue AS ts ';
                if (!empty($hashTagId)) {
                    $image_sub_query .= ' LEFT JOIN tissue_hashtags AS ths ON ths.tissue_id = ts.id' . PHP_EOL;
                }
                $image_sub_query .=  'where ts.`release_date` >= \'' . new Carbon($releaseDateLimit) . '\'
                and `ts`.`release_date` <= \'' . Carbon::now() . '\'
                and `ts`.`published_flg` = 1
                and `ts`.`tissue_status` = 1
                and (ts.cast_id = max(tissue.cast_id) or ts.guest_id = max(tissue.guest_id)
                or ts.mypage_id = max(tissue.mypage_id) or ts.night_cast_id = max(tissue.night_cast_id)) ';

                if (!empty($hashTagId)) {
                    $image_sub_query .= ' and ths.hashtag_id = \'' . $hashTagId . '\'';
                }

                $image_sub_query .= 'order by ts.set_top_status DESC, (ts.good_count + ts.add_good_count) desc,
                                    ts.view_count desc limit 1) as show_image';
            } else {
                $image_sub_query = '(select CASE
                WHEN image_url != \'\' THEN CONCAT(ts.id, \'-\',case
                WHEN old_mypage_id IS NOT NULL THEN CONCAT(\'oldMypage-image-\', old_mypage_id, \'-\')
                WHEN old_cast_id IS NOT NULL THEN CONCAT(\'oldCast-image-\', old_cast_id, \'-\')
                WHEN old_night_cast_id IS NOT NULL THEN CONCAT(\'oldNightCast-image-\', old_night_cast_id, \'-\')
                WHEN cast_id IS NOT NULL THEN \'cast-image-\'
                WHEN guest_id IS NOT NULL THEN \'guest-image-\'
                WHEN night_cast_id IS NOT NULL THEN \'nightCast-image-\'
                ELSE \'mypage-image-\' END, image_url)
                WHEN thumbnail_url != \'\' THEN CONCAT(ts.id, \'-\',case
                WHEN old_mypage_id IS NOT NULL THEN CONCAT(\'oldMypage-thumbnail-\', old_mypage_id, \'-\')
                WHEN old_cast_id IS NOT NULL THEN CONCAT(\'oldCast-thumbnail-\', old_cast_id, \'-\')
                WHEN old_night_cast_id IS NOT NULL THEN CONCAT(\'oldNightCast-thumbnail-\', old_night_cast_id, \'-\')
                when cast_id IS NOT NULL THEN \'cast-thumbnail-\'
                when guest_id IS NOT NULL THEN \'guest-thumbnail-\'
                WHEN night_cast_id IS NOT NULL THEN \'nightCast-thumbnail-\'
                ELSE \'mypage-thumbnail-\' END, thumbnail_url)
                WHEN movie_url != \'\' THEN CONCAT(ts.id, \'-\',case
                WHEN old_mypage_id IS NOT NULL THEN CONCAT(\'oldMypage-movie-\', old_mypage_id, \'-\')
                WHEN old_cast_id IS NOT NULL THEN CONCAT(\'oldCast-movie-\', old_cast_id, \'-\')
                WHEN old_night_cast_id IS NOT NULL THEN CONCAT(\'oldNightCast-movie-\', old_night_cast_id, \'-\')
                WHEN cast_id IS NOT NULL THEN \'cast-movie-\'
                WHEN guest_id IS NOT NULL THEN \'guest-movie-\'
                WHEN night_cast_id IS NOT NULL THEN \'nightCast-movie-\'
                ELSE \'mypage-movie-\' END, movie_url) ELSE NULL  END
                from tissue as ts
                where ts.release_date <= \'' . Carbon::now() . '\'';
                if ($isWeeklyReleaseDate === true) {
                    $image_sub_query .= ' AND `ts`.`release_date` between \'' . $weekStartDate . '\' AND \'' .  Carbon::now() . '\' ';
                }
                $image_sub_query .= '
                and ts.published_flg = 1
                and ts.`tissue_status` = 1
                and (ts.cast_id = max(tissue.cast_id) or ts.guest_id = max(tissue.guest_id)
                or ts.mypage_id = max(tissue.mypage_id) or ts.night_cast_id = max(tissue.night_cast_id))
                order by ts.set_top_status DESC, (ts.good_count + ts.add_good_count) desc, ts.view_count desc limit 1) as show_image';
            }
            $selectColsRaw = $image_sub_query . ',group_concat(tissue.id) as tissue_ids, max(tissue.id) as id ,
                            max(tissue.image_url) as image_url, max(tissue.movie_url) as movie_url,
                            max(tissue.thumbnail_url) as thumbnail_url, ';

            // Add poster column to determine which relation to use
            $selectColsRaw .= '
                MAX(CASE
                    WHEN tissue.guest_id IS NOT NULL THEN \'guest\'
                    WHEN tissue.mypage_id IS NOT NULL THEN \'mypage\'
                    WHEN tissue.cast_id IS NOT NULL THEN \'cast\'
                    WHEN tissue.shop_id IS NOT NULL THEN \'shop\'
                    WHEN tissue.night_cast_id IS NOT NULL THEN \'night-cast\'
                    WHEN tissue.night_shop_id IS NOT NULL THEN \'night-shop\'
                ELSE NULL
                END) AS poster,';

            $selectColsRaw .= '
                            max(tissue.cast_id) as cast_id, max(tissue.guest_id) as guest_id,
                            max(tissue.mypage_id) as mypage_id, max(tissue.shop_id) as shop_id,
                            max(tissue.night_cast_id) as night_cast_id,
                            max(tissue.night_shop_id) as night_shop_id,
                            max(casts.hide_flg) AS casts_hide_flg, max(cast_shop_mains.active_flg) AS cast_shop_active_flg,
                            max(casts.town_night_cast_id) AS choco_cast_night_cast_id,
                            sum(tissue.view_count) as view_count,
                            SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(tissue.image_attributes, 0)
                            ORDER BY set_top_status DESC, (tissue.good_count + tissue.add_good_count) DESC,
                            tissue.view_count DESC SEPARATOR \';\'),\';\',1) as image_attributes
                            , MAX(cast_shop_mains.night_town_id) AS choco_cast_shop_night_shop_id';
            if ($onlyCastAndGuest === false) {
                $selectColsRaw .= ', MAX(shop_mains.night_town_id) AS choco_shop_night_shop_id';
            }

            // personal ranking weekly point
            if ($isPersonalWeeklyList === true) {
                // Calculate weekly point (total goodcount、 total sns point and comment count)
                $weekCondition = self::getDateCondition($weekStartDate);
                $snsWeekCondition = self::getDateCondition($snsWeekStartDate);

                $totalGoodCountSql = 'SUM(' . sprintf($weekCondition, '(tissue.good_count + tissue.add_good_count)') . ')';
                $goodCountSql = 'SUM(' . sprintf($weekCondition, 'tissue.good_count') . ')';
                $addGoodCountSql = 'SUM(' . sprintf($weekCondition, 'tissue.add_good_count') . ')';
                $commentCountSql = 'SUM(' . $this->getTissueCommentCountSql($weekStartDate) . ')';
                $snsCountSql = 'SUM(' . sprintf($snsWeekCondition, 'tissue.sns_count') . ')';

                $selectColsRaw .= ', ' . $totalGoodCountSql . ' AS total_good_count
                                   , ' . $goodCountSql . ' as good_count, ' . $addGoodCountSql . ' as add_good_count
                                   , ' . $commentCountSql . ' as weekly_comment_count
                                   , ' . $snsCountSql . ' as sns_count';

                // tissue_point weekly
                $selectColsRaw .= ', (' . $totalGoodCountSql . ' + ' . $snsCountSql . ' + SUM('
                    . $this->getTissueCommentCountSql($weekStartDate)
                    . ')) AS tissue_point';

                $selectColsRaw .= ' , COUNT(CASE WHEN tissue.release_date >= \'' . $weekStartDate
                    . '\' THEN tissue.id ELSE NULL END) AS weekly_tissue_count';
            } else {
                $selectColsRaw .= ', sum(tissue.good_count + tissue.add_good_count) AS total_good_count
                                   , sum(tissue.good_count) as good_count, sum(tissue.add_good_count) as add_good_count
                                   , sum(tissue.sns_count) as sns_count';

                // If there is no specific tissue, get the sum of total point for all tissues
                $selectColsRaw .= ', ' . $this->getTissuePointSql();
            }
        } else {
            $selectColsRaw = 'tissue.*,
            (tissue.good_count + tissue.add_good_count) AS total_good_count,
            casts.hide_flg AS casts_hide_flg,
            cast_shop_mains.active_flg AS cast_shop_active_flg
            , casts.town_night_cast_id AS choco_cast_night_cast_id
            , (cast_shop_mains.night_town_id) AS choco_cast_shop_night_shop_id';
            if ($onlyCastAndGuest === false) {
                $selectColsRaw .= ', shop_mains.night_town_id AS choco_shop_night_shop_id';
            }
        }
        if (false === $onlyCastAndGuest) {
            $selectColsRaw .= ', shop_mains.active_flg AS shop_active_flg';
        }

        // LUM_ALL-1225 【夜遊び選手権】週間ランキングポイントの新実装
        if ($onlyCastAndGuest === true) {
            if ($isLikeSortList === true) {
                $selectColsRaw .= ', max(rp.total_point) AS rank_point';
            } else {
                $selectColsRaw .= ', rp.total_point AS rank_point';
                $selectColsRaw .= ', rp.tissue_from_type AS ranking_point_tissue_from_type';
            }
        }

        if (true === $isChampionship && !$isLikeSortList) {
            $selectColsRaw .= ', (SELECT min(tissue_comment.created_at) as created_at FROM tissue_comment
                LEFT JOIN tissue_comment as master_tissue_comment ON ( master_tissue_comment.id = tissue_comment.master_comment_id)
                WHERE tissue.id = tissue_comment.tissue_id AND tissue_comment.del = 0
                    AND (master_tissue_comment.del = 0 OR master_tissue_comment.del IS NULL)
                    AND tissue_comment.master_comment_id IS NULL
                    AND tissue_comment.reply_comment_id IS NULL
                GROUP BY tissue_comment.mypage_id, tissue_comment.cast_id
                    , tissue_comment.job_mypage_id, tissue_comment.job_staff_id
                    , tissue_comment.night_mypage_id, tissue_comment.night_cast_id
                ORDER BY created_at DESC LIMIT 1) AS last_comment_date';
        }
        // NOTE: エラー対応のため追加：tissue_type 追加もし恒常化する場合統合してください。
        $selectColsRaw .= ',"GIRL" as tissue_type';

        $with = [
            'shopMains',
            'shopMains.chocolatPic',
            'shopMains.area',
            'shopMains.prefecture',
            'shopMains.chocolatGenre',
            'shopMains.chocolatMens',
            'shopMains.nightTownShop',
            'shopMains.townNightAlignmentShops',
            'shopMains.townNightAlignmentShops.shop',
            'casts',
            'casts.todaysCastWorkDate',
            'castShopMains',
            'castShopMains.chocolatPic',
            'castShopMains.area',
            'castShopMains.prefecture',
            'castShopMains.chocolatGenre',
            'castShopMains.chocolatMens',
            'castShopMains.nightTownShop',
            'castShopMains.townNightAlignmentShops',
            'castShopMains.townNightAlignmentShops.shop',
            'guests',
            'chocoMypage',
            'hashTags',
            // night-shop
            'nightShopMains',
            'nightShopMains.chocoShopIds',
            'nightShopMains.mensChocoShopIds',
            // night-cast
            'nightCasts',
            'nightCastShopMains',
            'nightCastShopMains.chocoShopIds',
            'nightCastShopMains.mensChocoShopIds',
        ];

        $result = Tissue::query()
            ->select(DB::RAW($selectColsRaw))
            ->with($with)
            // except converting video (while one minute)
            ->where(function ($query) use ($slider, $new_movie) {
                if ($slider) {
                    $query->where('tissue.image_url', '<>', '');
                } else {
                    if (true === $new_movie) {
                        $query->where('tissue.movie_url', '<>', '')
                            ->where('tissue.created_at', '<', Carbon::now()->subMinutes(1)->toDateTimeString())
                            ->where('tissue.release_date', '>=', Carbon::now()->subDay()->toDateTimeString());
                    } else {
                        $query->where('tissue.image_url', '<>', '')
                            ->orWhere(function ($query) {
                                $query->where('tissue.movie_url', '<>', '')->where('tissue.created_at', '<',
                                    Carbon::now()->subMinutes(1)->toDateTimeString());
                            });
                    }
                }
            })->where('tissue.release_date', '<=', Carbon::now());

        if (!empty($pref)) {
            if ($onlyCastAndGuest === true) {
                $result
                    ->leftJoin('casts', 'casts.id', '=', 'tissue.cast_id')
                    ->leftJoin('shop_mains as cast_shop_mains', 'cast_shop_mains.id', '=', 'casts.shop_table_id')
                    ->leftJoin('tissueguests', 'tissueguests.id', '=', 'tissue.guest_id')
                    ->leftJoin('mypages', 'mypages.id', '=', 'tissue.mypage_id')
                    ->leftJoin('mypage_mains', 'mypage_mains.id', '=', 'tissue.mypage_id')
                    ->leftJoin('yoasobi_casts as night_casts', 'night_casts.id', '=', 'tissue.night_cast_id')
                    ->leftJoin('casts AS night_cast_bind_choco_cast', 'night_cast_bind_choco_cast.town_night_cast_id', '=', 'night_casts.id')
                    ->leftJoin('yoasobi_shops_all as night_cast_shop_mains', 'night_cast_shop_mains.id', '=', 'night_casts.shop_id')
                    ->where(function ($query) use ($pay_flg) {
                        $query->where('tissueguests.hide_flg', '=', 0)
                            ->orWhere(function ($query) use ($pay_flg) {
                                return $query->whereNotNull('tissue.cast_id')
                                    ->where('cast_shop_mains.pay_flg', '>=', $pay_flg);
                            })
                            ->orWhere(function ($query) {
                                $query->where('mypages.is_usable', '=', 1)
                                    ->where('mypage_mains.active_flg', '=', 1);
                            })
                            ->orWhereRaw("IFNULL(tissue.night_cast_id, 0) <> 0");
                    })
                    ->where(function ($query) use ($pref) {
                        $query->where('cast_shop_mains.pref_id', $pref)
                            ->orWhere('night_cast_shop_mains.prefecture_id', $pref);
                    });

                // left join ranking_point
                $result = $this->addRankingPointsJoin($result, $releaseDateLimit);

                // left join weekly_ranking_point
                if ($isPersonalWeeklyList === true) {
                    $result = $this->addWeeklyRankingPointsJoin($result, $lastWeekStartDate);
                }
            } else {
                $result->leftJoin('shop_mains', 'shop_mains.shop_id', '=', 'tissue.shop_id')
                    ->leftJoin('casts', 'casts.id', '=', 'tissue.cast_id')
                    ->leftJoin('shop_mains as cast_shop_mains', 'cast_shop_mains.id', '=', 'casts.shop_table_id')
                    ->leftJoin('yoasobi_casts as night_casts', 'night_casts.id', '=', 'tissue.night_cast_id')
                    ->leftJoin('yoasobi_shops_all as night_cast_shop_mains', 'night_cast_shop_mains.id', '=', 'night_casts.shop_id')
                    ->leftJoin('yoasobi_shops_all as night_shop_mains', 'night_shop_mains.id', '=', 'tissue.night_shop_id')
                    ->where(function ($query) use ($pref, $pay_flg) {
                        $query
                            ->where(function ($query) use ($pref, $pay_flg) {
                                return $query->where(
                                    'shop_mains.pay_flg',
                                    '>=',
                                    config('myapp.shop.plan.light_c.id')
                                )->where('shop_mains.pref_id', $pref);
                            })
                            ->orWhere(function ($query) use ($pref, $pay_flg) {
                                return $query->whereNotNull('tissue.cast_id')
                                    ->where('cast_shop_mains.pay_flg', '>=', $pay_flg)
                                    ->where('cast_shop_mains.pref_id', $pref);
                            })
                            ->orWhere(function ($query) use ($pref) {
                                return $query->whereNotNull('tissue.night_cast_id')
                                    ->where('night_cast_shop_mains.prefecture_id', $pref);
                            })->orWhere(function ($query) use ($pref) {
                                return $query->whereNotNull('tissue.night_shop_id')
                                    ->where('night_shop_mains.prefecture_id', $pref);
                            });
                    });
            }
        } else {
            if (true === $onlyCastAndGuest) {
                $result->leftJoin('casts', 'casts.id', '=', 'tissue.cast_id')
                    ->leftJoin('shop_mains as cast_shop_mains', 'cast_shop_mains.id', '=', 'casts.shop_table_id')
                    ->leftJoin('tissueguests', 'tissueguests.id', '=', 'tissue.guest_id')
                    ->leftJoin('mypages', 'mypages.id', '=', 'tissue.mypage_id')
                    ->leftJoin('mypage_mains', 'mypage_mains.id', '=', 'tissue.mypage_id')
                    ->leftJoin('yoasobi_casts as night_casts', 'night_casts.id', '=', 'tissue.night_cast_id')
                    ->leftJoin('casts AS night_cast_bind_choco_cast', 'night_cast_bind_choco_cast.town_night_cast_id', '=', 'night_casts.id')
                    ->leftJoin('yoasobi_shops_all as night_cast_shop_mains', 'night_cast_shop_mains.id', '=', 'night_casts.shop_id')
                    ->where(function ($query) use ($pay_flg) {
                        $query->where('tissueguests.hide_flg', '=', 0)
                            ->orWhere(function ($query) use ($pay_flg) {
                                return $query->whereNotNull('tissue.cast_id')
                                    ->where('cast_shop_mains.pay_flg', '>=', $pay_flg);
                            })
                            ->orWhere(function ($query) {
                                $query->where('mypages.is_usable', '=', 1)
                                    ->where('mypage_mains.active_flg', '=', 1);
                            })
                            ->orWhereRaw("IFNULL(tissue.night_cast_id, 0) <> 0");
                    });

                // left join ranking_point
                $result = $this->addRankingPointsJoin($result, $releaseDateLimit);

                // left join weekly_ranking_point
                if ($isPersonalWeeklyList === true) {
                    $result = $this->addWeeklyRankingPointsJoin($result, $lastWeekStartDate);
                }
            } else {
                $result->leftJoin('shop_mains', 'shop_mains.shop_id', '=', 'tissue.shop_id')
                    ->leftJoin('casts', 'casts.id', '=', 'tissue.cast_id')
                    ->leftJoin('shop_mains as cast_shop_mains', 'cast_shop_mains.id', '=', 'casts.shop_table_id')
                    ->leftJoin('tissueguests', 'tissueguests.id', '=', 'tissue.guest_id')
                    ->leftJoin('mypages', 'mypages.id', '=', 'tissue.mypage_id')
                    ->leftJoin('mypage_mains', 'mypage_mains.id', '=', 'tissue.mypage_id')
                    ->leftJoin('yoasobi_casts as night_casts', 'night_casts.id', '=', 'tissue.night_cast_id')
                    ->leftJoin('yoasobi_shops_all as night_cast_shop_mains', 'night_cast_shop_mains.id', '=', 'night_casts.shop_id')
                    ->where(function ($query) use ($pay_flg) {
                        $query->where('tissueguests.hide_flg', '=', 0)
                            ->orWhere(function ($query) use ($pay_flg) {
                                return $query->where('shop_mains.pay_flg', '>=', $pay_flg);
                            })->orWhere(function ($query) use ($pay_flg) {
                                return $query->whereNotNull('tissue.cast_id')
                                    ->where('cast_shop_mains.pay_flg', '>=', $pay_flg);
                            })
                            ->orWhere(function ($query) {
                                $query->where('mypages.is_usable', '=', 1)
                                    ->where('mypage_mains.active_flg', '=', 1);
                            })
                            ->orWhereRaw("IFNULL(tissue.night_cast_id, 0) <> 0")
                            ->orWhereRaw("IFNULL(tissue.night_shop_id, 0) <> 0");
                    });
            }
        }

        $result->where(function ($query) {
            if (!empty($this->chocoCastId)) {
                $query->where('tissue.cast_id', '=', $this->chocoCastId);
            }
            if (!empty($this->chocoMypageId)) {
                $query->orWhere('tissue.mypage_id', '=', $this->chocoMypageId);
            }
            if (!empty($this->guestId)) {
                $query->orWhere('tissue.guest_id', '=', $this->guestId);
            }
            if (!empty($this->nightCastId)) {
                $query->orWhere('tissue.night_cast_id', '=', $this->nightCastId);
                if (!empty($this->bindingChocoCastId)) {
                    $query->orWhere('tissue.cast_id', '=', $this->bindingChocoCastId);
                }
            }
        });

        if (!empty($releaseDateLimit)) {
            $result->where('tissue.release_date', '>=', new Carbon($releaseDateLimit));
        }

        if ($isWeeklyReleaseDate === true) {
            $result->whereBetween('tissue.release_date', [$weekStartDate, Carbon::now()]);
        }

        if ($isBeforeWeeklyReleaseDate === true) {
            $result->where('tissue.release_date', '<', $weekStartDate);
        }

        $result->where(function ($query) {
            if (!empty($this->chocoShopId)) {
                $query->where('cast_shop_mains.shop_id', '=', $this->chocoShopId);
            }

            if (!empty($this->nightShopId)) {
                $query->orWhere('night_cast_shop_mains.id', '=', $this->nightShopId);
            }
        });

        if (!empty($hashTagId)) {
            $result->leftJoin('tissue_hashtags', 'tissue_hashtags.tissue_id', '=', 'tissue.id');
            $result->where('tissue_hashtags.hashtag_id', '=', $hashTagId);
        }

        // Order By Sort
        if ($sort && $sort == self::PERSONAL_RANKING_SORT) {
            //NIGHT-1252 【ショコラ選手権】アイコさんの投稿を「イイネ順」「TOP10」に表示しないよう修正
            $result = $result->where(function ($query) {
                $query->whereNotIn('tissue.cast_id', self::FLITER_CAST_ID)->orWhereNull('tissue.cast_id');
            });

            $result->whereRaw("IFNULL(tissue.guest_id, 0) <> 38");

            if ($isLikeSortList) {
                if ($this->isBindCast === true) {
                    $nightCastBindChocoCastSql = DB::RAW("(CASE
                       WHEN night_cast_bind_choco_cast.id IS NOT NULL
                           THEN CONCAT(night_cast_bind_choco_cast.id, 'cc')
                       WHEN tissue.night_cast_id IS NOT NULL
                           THEN CONCAT(tissue.night_cast_id, 'nc')
                       WHEN tissue.cast_id IS NOT NULL
                           THEN CONCAT(tissue.cast_id, 'cc')
                       ELSE
                           NULL
                       END)");
                    $groupByArr = [
                        $nightCastBindChocoCastSql,
                        'tissue.guest_id',
                        'tissue.mypage_id',
                    ];
                } else {
                    $groupByArr = [
                        'tissue.cast_id',
                        'tissue.guest_id',
                        'tissue.mypage_id',
                        'tissue.night_cast_id',
                    ];
                }

                if (!empty($hashTagId)) {
                    $groupByArr[] = 'tissue_hashtags.hashtag_id';
                }
                $result = $result->groupBy($groupByArr);

                if (
                    $isPersonalRankingOrder === false
                    && $isPersonalWeeklyList === false
                ) {
                    $result = $result->orderBy('tissue_point', 'DESC');
                }
            }

            if ($isPersonalWeeklyList === true) {
                // personal ranking weekly order
                $result = $result
                    ->orderBy('total_good_count', 'DESC')
                    // ->orderBy('weekly_tissue_count', 'DESC')
                    ->orderByRaw("MAX(wrp.tissue_count) DESC")
                    ->orderByRaw("MAX(tissue.id) DESC");
            } elseif ($isPersonalRankingOrder === true) {
                // personal ranking point order
                $result = $result
                    ->orderByRaw("MAX(rp.total_point) DESC")
                    ->orderByRaw("MAX(tissue.id) DESC");
            } else {
                $result = $result->orderBy('total_good_count', 'DESC');
                $result = $result->orderBy('view_count', 'DESC');
            }
        } elseif ($sort && $sort == '3') {
            $result = $result->orderBy('tissue.order_num', 'ASC')->orderBy('tissue.id', 'DESC');
        } elseif ($sort && $sort == '4') {
            $likeTissueIDList = [];
            if (!empty(Cookie::get('likeTissueIDList'))) {
                $likeTissueIDList = json_decode(Cookie::get('likeTissueIDList'));
            }

            $result = $result->whereIn('tissue.id', $likeTissueIDList);
            $result = $result->orderByRaw(DB::raw("FIELD(tissue.id," . implode(',', $likeTissueIDList) . ") DESC"));
        } elseif ($sort && $sort == '5') {
            $result = $result->orderBy('tissue.release_date', 'DESC');
            if ($random) {
                $result = $result->inRandomOrder();
            }
        } elseif ($sort && ('6' == $sort)) {
            /**
             * NIGHT-763 【タイムライン】スライダー２カ所追加
             */
            $result = $result->orderByRaw('-tissue.slider_num DESC, tissue.id DESC');
        } else {
            if ($random) {
                $result = $result->inRandomOrder()->orderBy('tissue.release_date', 'DESC');
            } else {
                if ($isOsusumeOrder === true) {
                    $media = (true === Agent::isMobile()) ? 'sp' : 'pc';
                    $result = $result->join(
                        "tissue_recommend_{$media}",
                        'tissue.id',
                        '=',
                        "tissue_recommend_{$media}.tissue_id"
                    );
                    $orderBy = ["tissue_recommend_{$media}.id ASC"];
                } else {
                    $orderBy = ['tissue.id ASC'];
                    if (true === $isChampionship) {
                        $sql = 'CASE ';
                        $sql .= ' WHEN last_comment_date > release_date THEN last_comment_date ';
                        $sql .= ' ELSE release_date ';
                        $sql .= ' END DESC';

                        $orderBy = Arr::prepend($orderBy, $sql);
                    } else {
                        $orderBy = Arr::prepend($orderBy, 'tissue.release_date DESC');
                    }
                }

                $orderByRaw = implode(',', $orderBy);

                $result = $result->orderByRaw($orderByRaw);
            }
        }

        if (true === $pagenation) {
            $result = $result->published()->tissueStatusActive();
            $result_page = $result;
        } else {
            if (0 < $limit) {
                $result = $result->published()->tissueStatusActive()->limit($limit)->get();
            } else {
                $result = $result->published()->tissueStatusActive()->get();
            }
        }

        // Ranking Row
        if (!empty($pref)) {
            $rankingRows = $this->getEachPrefRanking(array($pref));
        } else {
            $rankingRows = $this->getEachPrefRanking($this->existsPref()->pluck('area_id'));
        }

        // Get Cookie
        $cookies = json_decode(Cookie::get(self::CookieName), true);

        // NOTE:暫定処理、このあたり置いておく at LUM_ALL-1108
        $nightCastIds = $result->pluck('night_cast_id')->filter();
        $chocoCastNightCastIds = $result->pluck('choco_cast_night_cast_id')->filter();

        $nightCastIds = $nightCastIds->merge($chocoCastNightCastIds);

        $nightCasts = self::getNightTownCastByCastIds($nightCastIds);
        $chocoCasts = self::getChocoCastByNightCastIds($nightCastIds);
        // TODO:上と合わせても問題無ければ合わせる at LUM_ALL-1108
        foreach ($result as $v) {
            if (!empty($v->night_cast_id)) {
                $castIds = [$v->night_cast_id];
            } elseif (!empty($v->casts->town_night_cast_id)) {
                $castIds = [$v->casts->town_night_cast_id];
            } else {
                $castIds = [];
            }
            $nightCasts = self::getNightTownCastByCastIds($castIds);
            $v->night_cast = $nightCasts->first();
        }

        $nightShopIds = [];
        $nightShopTableIdsToSearchChocoShops = [];
        $tissueIds = [];

        foreach ($result as $v) {
            if (!empty($v->choco_cast_shop_night_shop_id)) {
                $nightShopIds[] = $v->choco_cast_shop_night_shop_id;
            } elseif (!empty($v->choco_shop_night_shop_id)) {
                $nightShopIds[] = $v->choco_shop_night_shop_id;
            } elseif (!empty($v->night_shop_id)) {
                $nightShopIds[] = $v->night_shop_id;
                $nightShopTableIdsToSearchChocoShops[] = $v->night_shop_id;
            }

            if (!empty($nightCasts)) {
                $nightCast = $nightCasts->firstWhere('id', $v->night_cast_id);
                if (!empty($nightCast)) {
                    $nightShopTableIdsToSearchChocoShops[] = $nightCast->shop->id;
                }
            }

            if ($v->id) {
                $tissueIds[] = $v->id;
            }
        }

        $nightShops = self::getNightTownShopByNightShopIds(array_unique($nightShopIds));
        $chocoShops = ShopMain::with(['chocolatMens'])
            ->whereIn('night_town_id', $nightShopTableIdsToSearchChocoShops)
            ->get();

        if (!$isLikeSortList) {
            $tissueCommentCountArr = $this->getTissueCommentMaster($tissueIds, [], 0, 0, 0, true, true);
        } else {
            $tissueCommentCountArr = null;
        }

        $member = $this->getMemberFromCookie();

        $result = $result->each(function ($r) use ($rankingRows, $cookies, $nightShops, $nightCasts, $chocoShops, $chocoCasts, $tissueCommentCountArr, $weekStartDate, $member) {
            $this->attachDetailAttributes($member, $r, $rankingRows, $cookies, $nightShops, $nightCasts, $chocoShops, $chocoCasts, $tissueCommentCountArr, $weekStartDate);

            $isAutoGenerateGif = false;
            if (!empty($r->image_attributes)) {
                $imageAttributes = json_decode($r->image_attributes, true);
                $isAutoGenerateGif = $imageAttributes['isAutoGenerateGif'] ?? false;
            }

            if (!empty($r->show_image)) {
                $image_arr = explode('-', $r->show_image);

                $r->id = $image_arr[0];//get tissue id which has most like
                array_shift($image_arr);

                if ($image_arr[1] == 'image' || $image_arr[1] == 'thumbnail') {
                    $imgBasePath = (true === $isAutoGenerateGif) ? $this->twitterMoivePath() : $this->twitterPath();

                    if ($image_arr[0] == 'oldCast') {
                        $r->show_image = $imgBasePath . '/cast/' . $image_arr[2] . '/' . $image_arr[3];
                    } elseif ($image_arr[0] == 'oldMypage') {
                        $r->show_image = $imgBasePath . '/mypage/' . $image_arr[2] . '/' . $image_arr[3];
                    } elseif ($image_arr[0] == 'oldNightCast') {
                        $r->show_image = $imgBasePath . '/night-cast/' . $image_arr[2] . '/' . $image_arr[3];
                    } elseif ($image_arr[0] == 'guest') {
                        $r->show_image = $imgBasePath . '/guest/' . $r->guest_id . '/' . $image_arr[2];
                    } elseif ($image_arr[0] == 'cast') {
                        $r->show_image = $imgBasePath . '/cast/' . $r->cast_id . '/' . $image_arr[2];
                    } elseif ($image_arr[0] == 'mypage') {
                        $r->show_image = $imgBasePath . '/mypage/' . $r->mypage_id . '/' . $image_arr[2];
                    } elseif ($image_arr[0] == 'nightCast') {
                        $r->show_image = $imgBasePath . '/night-cast/' . $r->night_cast_id . '/' . $image_arr[2];
                    }

                    $r->show_image .= '?o=webp&type=resize&width=1000&height=1000&quality=95';
                }

                if ($image_arr[1] == 'movie') {
                    if ($image_arr[0] == 'oldCast') {
                        $imageName = explode('.', $image_arr[3])[0];
                        $r->show_image = $this->twitterMoivePath() . '/cast/' . $image_arr[2] . '/' . $imageName . self::THUMBNAIL_SECOND;
                    } elseif ($image_arr[0] == 'oldMypage') {
                        $imageName = explode('.', $image_arr[3])[0];
                        $r->show_image = $this->twitterMoivePath() . '/mypage/' . $image_arr[2] . '/' . $imageName . self::THUMBNAIL_SECOND;
                    } elseif ($image_arr[0] == 'oldNightCast') {
                        $imageName = explode('.', $image_arr[3])[0];
                        $r->show_image = $this->twitterMoivePath() . '/night-cast/' . $image_arr[2] . '/' . $imageName . self::THUMBNAIL_SECOND;
                    } elseif ($image_arr[0] == 'guest') {
                        $imageName = explode('.', $image_arr[2])[0];
                        $r->show_image = $this->twitterMoivePath() . '/guest/' . $r->guest_id . '/' . $imageName . self::THUMBNAIL_SECOND;
                    } elseif ($image_arr[0] == 'cast') {
                        $imageName = explode('.', $image_arr[2])[0];
                        $r->show_image = $this->twitterMoivePath() . '/cast/' . $r->cast_id . '/' . $imageName . self::THUMBNAIL_SECOND;
                    } elseif ($image_arr[0] == 'mypage') {
                        $imageName = explode('.', $image_arr[2])[0];
                        $r->show_image = $this->twitterMoivePath() . '/mypage/' . $r->mypage_id . '/' . $imageName . self::THUMBNAIL_SECOND;
                    } elseif ($image_arr[0] == 'nightCast') {
                        $imageName = explode('.', $image_arr[2])[0];
                        $r->show_image = $this->twitterMoivePath() . '/night-cast/' . $r->night_cast_id . '/' . $imageName . self::THUMBNAIL_SECOND;
                    }
                }
            }
        });
        return $result;
    }

    /**
     * get date week condition
     * @param string $startDate startdate (Y-m-d H:i:s)
     * @return string sql
     */
    private function getDateCondition($startDate)
    {
        $weekCondition = "CASE
            WHEN tissue.release_date >= '{$startDate}'
            THEN %s ELSE 0 END";

        return $weekCondition;
    }

    /**
     * Get the sql of tissue comment count
     * @param bool $isPersonalWeeklyList
     * @return string
     */
    private function getTissueCommentCountSql($date = null)
    {
        // personal weekly page
        $commentTimeSql = '';
        if (!empty($date)) {
            $commentTimeSql = ' AND tissue.release_date >= \'' . $date . '\'';
        }

        return '(select count(*) from tissue_comment
            left join tissue_comment as master_tissue_comment
            on (master_tissue_comment.id = tissue_comment.master_comment_id)
            where tissue.id = tissue_comment.tissue_id
            and tissue_comment.del = ' . 0
            . ' and (master_tissue_comment.del = ' . 0
            . ' or master_tissue_comment.del is null)
            ' . $commentTimeSql . '
            and case
                when tissue.cast_id is not null
                    then (tissue_comment.cast_id is null or tissue_comment.cast_id != tissue.cast_id)
                when tissue.mypage_id is not null
                    then (tissue_comment.mypage_id is null or tissue_comment.mypage_id != tissue.mypage_id)
                when tissue.night_cast_id is not null
                    then (tissue_comment.night_cast_id is null or tissue_comment.night_cast_id != tissue.night_cast_id)
                else
                    true
            end)';
    }

    /**
     * Calculate tissue point by sql
     *
     * @return string
     */
    private function getTissuePointSql()
    {
        return 'SUM(tissue.good_count + tissue.add_good_count + tissue.sns_count + '
            . $this->getTissueCommentCountSql()
            . ') AS tissue_point';
    }

    /**
     * ranking_points join
     * @param $query
     * @param $date
     * @return mixed
     */
    private function addRankingPointsJoin($query, $date)
    {
        $rankingPointQuery = RankingPoint::query()
            ->select(
                'choco_cast_id',
                'night_cast_id',
                'tissue_from_type',
                DB::raw(
                    "CASE WHEN tissue_from_type = 'GIRL_MYPAGE' THEN post_user_id ELSE NULL END AS choco_mypage_id"
                ),
                DB::raw("CASE WHEN tissue_from_type = 'GIRL_GUEST' THEN post_user_id ELSE NULL END AS choco_guest_id"),
                'total_point'
            )
            ->where('is_valid', '=', 1)
            ->where('championship_start_date', '=', new Carbon($date));

        return $query->leftJoinSub($rankingPointQuery, 'rp', function ($join) {
            $join
                ->on('tissue.cast_id', '=', 'rp.choco_cast_id')
                ->whereNotNull('tissue.cast_id')
                ->orOn('tissue.night_cast_id', '=', 'rp.night_cast_id')
                ->whereNotNull('tissue.night_cast_id')
                ->orOn('tissue.mypage_id', '=', 'rp.choco_mypage_id')
                ->whereNotNull('tissue.mypage_id')
                ->orOn('tissue.guest_id', '=', 'rp.choco_guest_id')
                ->whereNotNull('tissue.guest_id');
        });
    }

    /**
     * add weekly_ranking_points join
     * @param $query
     * @param $date
     * @return mixed
     */
    private function addWeeklyRankingPointsJoin($query, $date)
    {
        $weeklyRankingPointQuery = WeeklyRankingPoint::query()
            ->select(
                'choco_cast_id',
                'night_cast_id',
                DB::raw(
                    "CASE WHEN tissue_from_type = 'GIRL_MYPAGE' THEN post_user_id ELSE NULL END AS choco_mypage_id"
                ),
                DB::raw("CASE WHEN tissue_from_type = 'GIRL_GUEST' THEN post_user_id ELSE NULL END AS choco_guest_id"),
                'point',
                'tissue_count'
            )
            ->where('is_aggregated', '=', 1)
            ->where('ranking_cumulative_start_date', '=', $date);

        return $query->leftJoinSub($weeklyRankingPointQuery, 'wrp', function ($join) {
            $join
                ->on('tissue.cast_id', '=', 'wrp.choco_cast_id')
                ->whereNotNull('tissue.cast_id')
                ->orOn('tissue.night_cast_id', '=', 'wrp.night_cast_id')
                ->whereNotNull('tissue.night_cast_id')
                ->orOn('tissue.mypage_id', '=', 'wrp.choco_mypage_id')
                ->whereNotNull('tissue.mypage_id')
                ->orOn('tissue.guest_id', '=', 'wrp.choco_guest_id')
                ->whereNotNull('tissue.guest_id');
        });
    }

    /**
     * get Ranking Shop
     * @param array $prefecture_ids
     * @return mixed
     */
    public function getEachPrefRanking($prefecture_ids = array())
    {
        $sql = NightShop::query()
            ->select('shops.choco_shop_id', 'shops.prefecture_id', 'ranking_scores.score', 'shops.rank', 'shops.plan', 'shops.id')
            ->whereIn('prefecture_id', $prefecture_ids)->published()
            ->leftJoin('ranking_scores', 'shops.id', 'ranking_scores.shop_id')
            ->where('shops.is_closed', false)
            ->orderBy('ranking_scores.score', 'DESC')
            ->orderBy('shops.rank', 'ASC')
            ->orderBy('shops.plan', 'DESC')
            ->orderBy('shops.id')
            ->get();

        return $this->groupByPrefSort($sql);
    }

    /**
     * Collection Group By Pref
     * @param $collection
     * @return mixed
     */
    public function groupByPrefSort($collection)
    {
        $collection = $collection->groupBy('prefecture_id');


        $collection = $collection->map(function ($item) {
            return $item->take(30);
        });

        return $collection;
    }

    /**
     * get exists Pref
     * @param bool $onlyCastAndGuest
     * @param null $sort
     * @param null $releaseDateLimit
     * @param int $payFlg
     * @return array|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function existsPref(
        $onlyCastAndGuest = false,
        $sort = null,
        $releaseDateLimit = null,
        $payFlg = 5
    ) {
        if (true === $onlyCastAndGuest) {
            $query = Tissue::query()
                ->select(DB::raw('COALESCE(shop_mains.pref_id, night_cast_shop_mains.prefecture_id) AS pref_id'))
                ->leftJoin('casts', 'casts.id', '=', 'tissue.cast_id')
                ->leftJoin('shop_mains', 'shop_mains.id', '=', 'casts.shop_table_id')
                ->leftJoin('tissueguests', 'tissueguests.id', '=', 'tissue.guest_id')
                ->leftJoin('mypages', 'mypages.id', '=', 'tissue.mypage_id')
                ->leftJoin('mypage_mains', 'mypage_mains.id', '=', 'tissue.mypage_id')
                ->leftJoin('yoasobi_casts as night_casts', 'night_casts.id', '=', 'tissue.night_cast_id')
                ->leftJoin('yoasobi_shops_all as night_cast_shop_mains', 'night_cast_shop_mains.id', '=', 'night_casts.shop_id')
                ->where(function ($query) use ($payFlg) {
                    $query->where('tissueguests.hide_flg', '=', 0)
                        ->orWhere(function ($query) use ($payFlg) {
                            return $query->whereNotNull('tissue.cast_id')->where('shop_mains.pay_flg', '>=', $payFlg);
                        })
                        ->orWhere(function ($query) {
                            $query->where('mypages.is_usable', '=', 1)->where('mypage_mains.active_flg', '=', 1);
                        })
                        ->orWhere(function ($query) {
                            return $query->whereNotNull('tissue.night_cast_id');
                        });
                });
            if ($sort && $sort == 4) {
                $likeTissueIDList = array();
                if (!empty(Cookie::get('likeTissueIDList'))) {
                    $likeTissueIDList = json_decode(Cookie::get('likeTissueIDList'));
                }

                $query = $query->whereIn('tissue.id', $likeTissueIDList);
            } elseif ($sort && '7' == $sort) {
                $query =
                    $query->where('shop_mains.active_flg', '=', 1)
                        ->where('shop_mains.test_shop', '=', 0)
                        ->where('shop_mains.close_flg', '=', 0);
            }

            if ($sort && $sort == '2') {
                //NIGHT-1252 【ショコラ選手権】アイコさんの投稿を「イイネ順」「TOP10」に表示しないよう修正
                $query = $query->where(function ($query) {
                    $query->whereNotIn('tissue.cast_id', self::FLITER_CAST_ID)->orWhereNull('tissue.cast_id');
                });
            }
        } else {
            $query = Tissue::query()
                ->select(
                    DB::raw('COALESCE(shop_mains.pref_id, shop_cast_mains.pref_id, night_shops.prefecture_id,
                        night_cast_shop_mains.prefecture_id) as pref_id')
                )
                ->leftJoin('shop_mains', 'shop_mains.shop_id', '=', 'tissue.shop_id')
                ->leftJoin('casts', 'casts.id', '=', 'tissue.cast_id')
                ->leftJoin('shop_mains as shop_cast_mains', 'shop_cast_mains.id', '=', 'casts.shop_table_id')
                ->leftJoin('tissueguests', 'tissueguests.id', '=', 'tissue.guest_id')
                ->leftJoin('mypages', 'mypages.id', '=', 'tissue.mypage_id')
                ->leftJoin('mypage_mains', 'mypage_mains.id', '=', 'tissue.mypage_id')
                ->leftJoin('yoasobi_shops_all AS night_shops', 'night_shops.id', '=', 'tissue.night_shop_id')
                ->leftJoin('yoasobi_casts AS night_casts', 'night_casts.id', '=', 'tissue.night_cast_id')
                ->leftJoin('yoasobi_shops_all AS night_cast_shop_mains', 'night_cast_shop_mains.id', '=', 'night_casts.shop_id')
                ->where(function ($query) use ($payFlg) {
                    return $query->where('tissueguests.hide_flg', '=', 0)
                        ->orWhere(function ($query) use ($payFlg) {
                            return $query->where('shop_mains.pay_flg', '>=', $payFlg);
                        })
                        ->orWhere(function ($query) use ($payFlg) {
                            return $query->whereNotNull('tissue.cast_id')
                                ->where('shop_cast_mains.pay_flg', '>=', $payFlg);
                        })
                        ->orWhere(function ($query) {
                            $query->where('mypages.is_usable', '=', 1)->where('mypage_mains.active_flg', '=', 1);
                        })
                        ->orWhere(function ($query) {
                            return $query->whereNotNull('tissue.night_shop_id');
                        })
                        ->orWhere(function ($query) {
                            return $query->whereNotNull('tissue.night_cast_id');
                        });
                });
        }

        if (!empty($releaseDateLimit)) {
            $query = $query->where('tissue.release_date', '>=', new Carbon($releaseDateLimit));
        }

        $query = $query->where('tissue.release_date', '<=', Carbon::now());

        $query =
            $query
                // except converting video (while one minute)
                ->where(function ($query) {
                    $query->where('image_url', '<>', '')
                        ->orWhere(function ($query) {
                            $query->where('movie_url', '<>', '')->where('tissue.created_at', '<',
                                Carbon::now()->subMinutes(1)->toDateTimeString());
                        });
                })
                ->published()
                ->tissueStatusActive()
                ->distinct()
                ->get();

        $pref_ids = $query->pluck('pref_id');

        $existsPref = AreaPref::query()->whereIn('area_id', $pref_ids)->get();

        //if tissue is from guest or mypage, also need to show like history link button in championship page
        if ($existsPref->isEmpty() && $pref_ids->isNotEmpty()) {
            $existsPref[] = -1;
        }

        return $existsPref;
    }

    /**
     * get night cast by night cast ids
     * @param $castIds
     * @return mixed
     */
    private function getNightTownCastByCastIds($castIds)
    {
        $res = NightCast::query()
            ->whereIn('id', $castIds)
            ->get();

        return $res;
    }

    /**
     * get choco cast by night cast ids
     * @param $castIds
     * @return mixed
     */
    private function getChocoCastByNightCastIds($castIds)
    {
        return Cast::with(['shopMains'])
            ->whereIn('town_night_cast_id', $castIds)
            ->get();
    }

    private function getNightTownShopByNightShopIds($shopIds)
    {
        $res = NightShop::query()
            ->whereIn('id', $shopIds)
            ->get();

        return $res;
    }

    /**
     * Get tissue comments or the count of comments
     *
     * @param array|integer|string $tissueId    The ID of the tissue.
     * @param array|integer        $currentIds  The IDs of the current comments.
     * @param integer              $perPage     The number of items per page.
     * @param integer              $currentPage The current page number.
     * @param integer|null         $masterId    The ID of the master comment.
     * @param boolean              $count       If true, return the count of comments.
     * @param boolean              $all         If true, return all comments including replies.
     *                                          If false, return only top-level comments.
     * @param integer              $skipAdd     The number of items to skip.
     * @param boolean              $sendComment If true, return the comment that was just sent.
     * @param $weekStartDate
     * @return array|integer
     */
    public function getTissueCommentMaster(
        $tissueId,
        $currentIds,
        int $perPage,
        int $currentPage,
        ?int $masterId = 0,
        bool $count = false,
        bool $all = false,
        int $skipAdd = 0,
        bool $sendComment = false,
        $weekStartDate = null
    ) {
        // If count is true, return the count of comments.
        if ($count === true) {
            // If masterId exists, return the count of the replies to the comment.
            if ($masterId > 0) {
                return $this->getCommentReplyCount($masterId);
            }

            if (empty($tissueId)) {
                return 0;
            }

            $tissueIdsArr = $tissueId;
            if (is_array($tissueIdsArr) === false) {
                $tissueIdsArr = [$tissueIdsArr];
            }
            $counts = $this->getTissueCommentMasterCount($tissueIdsArr);
            return is_array($tissueId) === false ? $counts[intval($tissueId)] : $counts;
        }

        $selectSql = 'tissue_comment.id,
                tissue_comment.content,
                tissue_comment.created_at,
                tissue_comment.master_comment_id,
                tissue_comment.reply_comment_id,
                tissue_comment.night_mypage_id,
                tissue_comment.night_cast_id,
                tissue_comment.good_count,
                tissue_comment.author_like,
                tissue_comment.tissue_id,
                tissue_comment.sns_account,
                tissue_comment.is_sns_login,

                tissue.cast_id as tissue_cast_id,
                tissue.mypage_id as tissue_mypage_id,
                tissue.night_cast_id as tissue_night_cast_id,

                tissue_comment.mypage_id as mypage_id,
                mypage_mains.nickname,
                mypage_mains.profile_image,
                mypage_mains.created_at as mypage_created,
                mypage_mains.active_flg as mypage_main_active_flg,

                tissue_comment.cast_id as cast_id,
                casts.hide_flg as cast_hide_flg,
                shop_mains.active_flg as cast_shop_main_active_flg,
                shop_mains.test_shop as shop_main_test_shop,

                casts.cast_name,
                casts.cast_photo,
                shop_mains.id as shop_main_id';
        $result = TissueComment::onWriteConnection()
            ->leftJoin('tissue', 'tissue.id', '=', 'tissue_comment.tissue_id')
            ->leftJoin('mypage_mains', 'mypage_mains.id', '=', 'tissue_comment.mypage_id')
            ->leftjoin('casts', 'casts.id', '=', 'tissue_comment.cast_id')
            ->leftJoin('shop_mains', 'shop_mains.id', '=', 'casts.shop_table_id')
            ->leftJoin('tissue_comment as master_tissue_comment', 'master_tissue_comment.id', '=',
                'tissue_comment.master_comment_id')
            ->where(function ($query) {
                $query->whereNull('master_tissue_comment.del')->orWhere('master_tissue_comment.del', '=', 0);
            })
            ->when(!empty($weekStartDate), function ($query) use ($weekStartDate) {
                $query->whereBetween('tissue.release_date', [$weekStartDate, Carbon::now()]);
            });

        if ($masterId > 0) {
            $selectSql .= ',reply_mypage_mains.id as reply_mypage_id,
                reply_mypage_mains.nickname as reply_nickname,
                reply_mypage_mains.created_at as reply_mypage_created,
                reply_casts.id as reply_cast_id,
                reply_casts.cast_name as reply_cast_name,
                reply_tissue_comment.night_mypage_id as reply_night_mypage_id,
                reply_mypage_mains.active_flg as reply_mypage_main_active_flg,
                reply_casts.hide_flg as reply_cast_hide_flg,
                reply_shop_mains.active_flg as reply_cast_shop_main_active_flg,
                reply_shop_mains.test_shop as reply_shop_main_test_shop';

            $result = $result->leftjoin('tissue_comment as reply_tissue_comment', 'reply_tissue_comment.id', '=',
                'tissue_comment.reply_comment_id')
                ->leftJoin('mypage_mains as reply_mypage_mains', 'reply_mypage_mains.id', '=',
                    'reply_tissue_comment.mypage_id')
                ->leftjoin('casts as reply_casts', 'reply_casts.id', '=', 'reply_tissue_comment.cast_id')
                ->leftJoin('shop_mains as reply_shop_mains', 'reply_shop_mains.id', '=',
                    'reply_casts.shop_table_id');

            $result = $result->where('tissue_comment.master_comment_id', '=', $masterId);
        } else {
            if (is_array($tissueId)) {
                $result = $result->whereIn('tissue_comment.tissue_id', $tissueId);
            } else {
                $result = $result->where('tissue_comment.tissue_id', '=', $tissueId);
            }

            if (!$all) {
                $result = $result->whereNull('tissue_comment.master_comment_id');
            }
        }

        $result = $result->select(DB::RAW($selectSql));

        if ($sendComment) {
            $result = $result->where('tissue_comment.id', $currentIds);
        } else {
            if (!empty($currentIds)) {
                $result = $result->whereNotIn('tissue_comment.id', $currentIds);
            }
        }

        if ($masterId > 0) {
            $result = $result->orderBy('tissue_comment.created_at', 'ASC');
        } else {
            $result = $result->orderBy('tissue_comment.created_at', 'DESC');
        }

        $result = $result->notDeleted()->PageCtrl($perPage, $currentPage, $skipAdd);

        $result['items'] = $result['items']->each(function ($r) {
            $this->attachCommentAttributes($r);
        });

        return $result;
    }

    public function attachCommentAttributes($r)
    {
        if (!empty($r->mypage_id)) {

            if (!empty($r->nickname) && $r->mypage_main_active_flg == 1) {
                $r->show_name = $r->nickname;
            } else {
                $r->show_name = 'userC' . substr(md5($r->mypage_id), 0, 6);
            }

            if (!empty($r->profile_image)) {
                $r->show_photo = $this->chocoMypagePath() . '/' . $r->mypage_id . '/' . $r->profile_image;
            }

        } elseif (!empty($r->cast_id)) {
            if (!empty($r->cast_name)) {
                $r->show_name = $r->cast_name;
            } else {
                $r->show_name = 'userC' . substr(md5($r->cast_id), 0, 6);
            }
            if (!empty($r->cast_photo)) {
                $r->show_photo = $this->shopPath() . '/' . $r->shop_main_id . '/casts/' . $r->cast_photo;
            }
        } elseif (!empty($r->night_mypage_id)) {
            $member = Member::where('id', $r->night_mypage_id)->first();
            if (!empty($member)) {
                $r->show_name = $member->real_name;

                if (!empty($member->real_img_plofile_path)) {
                    $r->show_photo = $member->real_img_plofile_path;
                }
            } else {
                $r->show_name = 'UserN' . substr(md5($r->night_mypage_id), 0, 6);
            }
        } elseif (!empty($r->night_cast_id)) {
            $member = NightCast::where('id', $r->night_cast_id)->first();
            if (!empty($member)) {
                $r->show_name = $member->real_name;

                if (!empty($member->real_img_plofile_path)) {
                    $r->show_photo = $member->real_img_plofile_path;
                }
            } else {
                $r->show_name = 'UserN' . substr(md5($r->night_mypage_id), 0, 6);
            }
        }

        if (empty($r->show_photo)) {
            $r->show_photo = '/img/user/common/noimage-mypage.webp';
        } else {
            $r->show_photo = $r->show_photo . '?o=webp&type=resize&width=275&height=275&quality=95';
        }

        $passMin = $r->created_at->diffInMinutes((new Carbon), true);
        $passDay = $r->created_at->diffInDays((new Carbon), true);

        if ($passDay > 0) {
            if ($passDay > 7) {
                $r->show_time = $r->created_at->format('Y年m月d日');
            } else {
                $r->show_time = $passDay . '日前';
            }
        } else {
            if ($passMin < 60) {
                $r->show_time = $passMin . '分前';
            } else {
                $r->show_time = floor($passMin / 60) . '時間前';
            }
        }

        $r->childCommentCount = $this->getTissueCommentMaster(0, [], 0, 0, $r->id, true);

        //reply to name
        $r->reply_show_name = '';
        if ($r->master_comment_id != $r->reply_comment_id) {
            if (!empty($r->reply_mypage_id) && $r->reply_mypage_main_active_flg == 1) {
                if (!empty($r->reply_nickname)) {
                    $r->reply_show_name = $r->reply_nickname;
                } else {
                    $r->reply_show_name = 'user' . substr(md5($r->reply_mypage_id), 0, 6);
                }
            } elseif (!empty($r->reply_cast_id)) {
                if ($r->reply_cast_hide_flg != 0 || $r->reply_shop_main_active_flg != 1 || $r->reply_shop_main_test_shop != 0) {
                    $r->reply_show_name = 'userC' . substr(md5($r->reply_cast_id), 0, 6);
                } else {
                    $r->reply_show_name = $r->reply_cast_name;
                }
            } elseif (!empty($r->reply_night_mypage_id)) {
                $member = Member::where('id', $r->reply_night_mypage_id)->first();
                if (!empty($member)) {
                    $r->reply_show_name = $member->real_name;
                } else {
                    $r->reply_show_name = 'UserN' . substr(md5($r->night_mypage_id), 0, 6);
                }
            }
        }

        $cookies = json_decode(Cookie::get('likeComment'), true);

        if (!empty($cookies[$r->id])) {
            $r->is_liked = 1;
        } else {
            $r->is_liked = 0;
        }

        $r->is_tissue_author = 0;
        if (!empty($r->tissue_cast_id)) {
            if ($r->tissue_cast_id == $r->cast_id) {
                $r->is_tissue_author = 1;
            }
        } elseif (!empty($r->tissue_mypage_id)) {
            if ($r->tissue_mypage_id == $r->mypage_id) {
                $r->is_tissue_author = 1;
            }
        } elseif (!empty($r->tissue_night_cast_id)) {
            if ($r->tissue_night_cast_id == $r->night_cast_id) {
                $r->is_tissue_author = 1;
            }
        }

        $r->content = htmlspecialchars($r->content);
        $r->show_name = htmlspecialchars($r->show_name);
        $r->reply_show_name = htmlspecialchars($r->reply_show_name);
        unset($r->tissue_cast_id, $r->cast_id, $r->cast_name, $r->shop_main_id, $r->mypage_created, $r->profile_image, $r->nickname, $r->mypage_id, $r->cast_photo, $r->created_at);
        unset($r->night_mypage_id, $r->master_comment_id, $r->reply_comment_id, $r->reply_mypage_id, $r->reply_nickname, $r->reply_mypage_created, $r->reply_cast_id, $r->reply_cast_name, $r->reply_night_mypage_id);
        unset($r->mypage_main_active_flg, $r->cast_hide_flg, $r->cast_shop_main_active_flg, $r->shop_main_test_shop, $r->reply_mypage_main_active_flg, $r->reply_cast_hide_flg, $r->reply_cast_shop_main_active_flg, $r->reply_shop_main_test_shop);
    }

    public function chocoMypagePath()
    {
        if (App::environment() === 'production') {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN') . '/mypage/img';
        } else {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN_RELEASE') . '/mypage/img';
        }
    }

    public function shopPath()
    {
        if (App::environment() === 'production') {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN') . '/shop/img';
        } else {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN_RELEASE') . '/shop/img';
        }
    }

    public function twitterMoivePath()
    {
        if (App::environment() === 'production') {
            return 'https://s3-ap-northeast-1.amazonaws.com/encodefiles.chocolat.work/mens/twitter';
        } else {
            return 'https://s3-ap-northeast-1.amazonaws.com/encodedevfiles.chocolat.work/mens/twitter';
        }
    }

    /**
     * Get Twitter Path
     * @return mixed
     */
    public function twitterPath()
    {
        if (App::environment() === 'production') {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN') . '/twitter';
        } else {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN_RELEASE') . '/twitter';
        }
    }

    /**
     * Summary of getMemberFromCookie
     *
     * @return Cast|object|\Illuminate\Database\Eloquent\Model|\Illuminate\Foundation\Auth\User|null
     */
    private function getMemberFromCookie()
    {
        $member = NightCast::where('api_token', Cookie::get('yoasobiCastApiToken'))->first();

        if (empty($member)) {
            $member = auth()->guard('member')->user();
        }

        return $member;
    }

    /**
     * tissueレコードに詳細情報の付与
     * @param $r
     * @param $rankingRows
     * @param $cookies
     * @param $nightShops
     * @param null $nightCasts
     * @param null $chocoShops
     * @param null $chocoCasts
     * @param null $tissueCommentCountArr
     * @return mixed
     */
    public function attachDetailAttributes(
        $member,
        $r,
        $rankingRows,
        $cookies,
        $nightShops,
        $nightCasts = null,
        $chocoShops = null,
        $chocoCasts = null,
        $tissueCommentCountArr = null,
        $weekStartDate = null
    ) {
        if ($r->total_good_count <= 0) {
            $r->total_good_count = 0;
        }

        if (!empty($r->cast_id) && !empty($r->choco_cast_night_cast_id)) {
            if (!empty($nightCasts)) {
                $nightCast = $nightCasts->firstWhere('id', $r->choco_cast_night_cast_id);

                if (!empty($nightCast)) {
                    $r->night_cast = $nightCast;
                }
            }

            if (!empty($r->choco_cast_shop_night_shop_id)) {
                $nightShop = $nightShops->firstWhere('id', $r->choco_cast_shop_night_shop_id);
                if (!empty($nightShop)) {
                    $r->night_shop = $nightShop;
                }
            }
        } elseif (!empty($r->choco_cast_shop_night_shop_id)) {
            $nightShop = $nightShops->firstWhere('id', $r->choco_cast_shop_night_shop_id);

            if (!empty($nightShop)) {
                $r->night_shop = $nightShop;
            }
        } elseif (!empty($r->choco_shop_night_shop_id)) {
            $nightShop = $nightShops->firstWhere('id', $r->choco_shop_night_shop_id);

            if (!empty($nightShop)) {
                $r->night_shop = $nightShop;
            }
        } elseif (!empty($r->night_shop_id)) {
            $nightShop = $nightShops->firstWhere('id', $r->night_shop_id);
            $chocoShop = $chocoShops->firstWhere('night_town_id', $r->night_shop_id);

            if (!empty($nightShop)) {
                $r->night_shop = $nightShop;
            }
            if (!empty($chocoShop)) {
                $r->night_related_choco_shop = $chocoShop;
            }
        } elseif (!empty($r->night_cast_id)) {
            if (!empty($nightCasts)) {
                $nightCast = $nightCasts->firstWhere('id', $r->night_cast_id);

                if (!empty($nightCast)) {
                    $r->night_cast = $nightCast;

                    if (!empty($nightCast->shop)) {
                        $r->night_shop = $nightCast->shop;
                        $chocoShop = $chocoShops->firstWhere('night_town_id', $r->night_shop->id);

                        if (!empty($chocoShop)) {
                            $r->night_related_choco_shop = $chocoShop;
                        }
                    }
                }
            }

            if (!empty($chocoCasts)) {
                $chocoCast = $chocoCasts->firstWhere('town_night_cast_id', $r->night_cast_id);

                if (!empty($chocoCast)) {
                    $r->night_related_choco_cast = $chocoCast;
                }
            }
        }

        $r->ranking = '';

        if (
            !empty($r->night_shop)
            && $r->night_shop->plan > 5
            && $r->night_shop->status_id === 4
            && (!empty($r->night_shop_id) || !empty($r->night_cast_id))
            && ($this->isBindCast === false || empty($r->night_related_choco_cast))
        ) {
            $prefectureId = $r->night_shop->prefecture_id;
            if (!empty($prefectureId)) {
                $data = collect($rankingRows[$prefectureId] ?? [])->where('id', $r->night_shop->id);
                if ($data->isNotEmpty()) {
                    $r->ranking = $data->keys()->first() + 1;
                }
            }
        }

        $r->comment_count = 0;
        $r->member_comment_exisits = false;

        if (is_null($tissueCommentCountArr)) {
            if (mb_strlen($r->tissue_ids) > 1020) {
                $_query = Tissue::query()->select('id');
                if (!empty($r->shop_id)) {
                    $_query->where('shop_id', $r->shop_id);
                } else if (!empty($r->cast_id)) {
                    $_query->where('cast_id', $r->cast_id);
                } else if (!empty($r->guest_id)) {
                    $_query->where('guest_id', $r->guest_id);
                } else if (!empty($r->mypage_id)) {
                    $_query->where('mypage_id', $r->mypage_id);
                } else if (!empty($r->night_shop_id)) {
                    $_query->where('night_shop_id', $r->night_shop_id);
                } else if (!empty($r->night_cast_id)) {
                    $_query->where('night_cast_id', $r->night_cast_id);
                }

                $_query
                    ->where('release_date', '>=', '2025-07-15')
                    ->where('release_date', '<=', Carbon::now())
                    ->published()
                    ->tissueStatusActive();

                $r->tissue_ids = $_query->get()->pluck('id')->implode(',');
            }

            $r->comment_count = $this->getTissueCommentMaster(
                explode(',', $r->tissue_ids),
                [],
                0,
                0,
                0,
                true,
                true,
                0,
                false,
                $weekStartDate
            );
            $r->comment_count = $r->comment_count->sum();
        } else {
            if (isset($tissueCommentCountArr[$r->id])) {
                $r->comment_count = $tissueCommentCountArr[$r->id];

                if (true === !empty($member) && $r->comment_count > 0) {
                    $r->member_comment_exisits = $this->getCommentCountByMemberId($member, null, false, false,
                            $r->id) > 0;
                }
            }
        }

        if (isset($r->weekly_comment_count)) {
            $r->comment_count_format = ($r->weekly_comment_count > 0)
                ? $this->numberFormat($r->weekly_comment_count)
                : '';
        } else {
            $r->comment_count_format = (0 < $r->comment_count) ? $this->numberFormat($r->comment_count) : '';
        }

        $r->countview = $this->countView($r);

        if ($r->image_url) {
            $shopId = null;
            if (isset($r->shopMains)) {
                $shopId = $r->shopMains->getOriginal('shop_id');
            }
            $filePath = self::makeTissueImageUrl(
                $r->old_mypage_id,
                $r->old_cast_id,
                $r->old_night_cast_id,
                $r->cast_id,
                $r->guest_id,
                $r->mypage_id,
                $shopId,
                $r->night_cast_id,
                $r->night_shop_id,
                $r->image_url,
                null,
                null,
                true,
                false
            );
            $r->image_url = $this->twitterPath() . $filePath . $r->image_url_resize;
        } else {
            $r->image_url = '';// if $r->image_url = null, frontend maybe error;
        }

        if ($r->movie_url) {
            $shopId = null;
            if (isset($r->shopMains)) {
                $shopId = $r->shopMains->getOriginal('shop_id');
            }
            $filePath = self::makeTissueImageUrl(
                $r->old_mypage_id,
                $r->old_cast_id,
                $r->old_night_cast_id,
                $r->cast_id,
                $r->guest_id,
                $r->mypage_id,
                $shopId,
                $r->night_cast_id,
                $r->night_shop_id,
                null,
                null,
                $r->movie_url,
                true,
                true
            );

            $imageName = explode('.', $r->movie_url)[0];

            $r->movie_image_url = $this->twitterMoivePath() . $filePath . $imageName . self::THUMBNAIL_SECOND;
            $r->movie_thumbnail_url = (empty($r->thumbnail_url)) ? $r->movie_image_url : $this->twitterPath() . $filePath . $r->thumbnail_url;
            $r->movie_url = $this->twitterMoivePath() . $filePath . $r->movie_url;
        } else {
            $r->movie_url = ''; // if $r->movie_url = null, frontend maybe error;
        }

        if (!empty($r->image_attributes)) {
            $imageAttributes = json_decode($r->image_attributes, true);
            $isAutoGenerateGif = $imageAttributes['isAutoGenerateGif'] ?? false;
            if (!empty($imageAttributes['sticker'])) {
                $stickerFileType = (true === Agent::isMobile()) ? 'sp' : 'pc';
                $sticker = "/img/user/common/promotion/stickers/{$stickerFileType}/{$imageAttributes['sticker']}";
                $imageAttributes['sticker'] = secure_asset($sticker);
            }

            if (true === $isAutoGenerateGif) {
                $r->movie_image_url = $this->twitterMoivePath() . $filePath . $r->thumbnail_url;
                $r->movie_thumbnail_url = $r->movie_image_url;
            }

            $r->image_attributes = json_encode($imageAttributes);
        }

        $r->shop_image = $r->image_url ?? '/img/user/common/noimage-chocolat-girl.webp';

        //check login
        if (!empty($member)) {
            $this->likeCount = 20;
        }

        if ($cookies && array_key_exists($r->id, $cookies)) {
            $r->liked_before = true;
            if ($cookies[$r->id] >= $this->likeCount) {
                $r->liked = true;
            } else {
                $r->liked = false;
            }
        } else {
            $r->liked_before = false;
        }

        // 公式アカウントの場合、イイネできない様にする。暫定対応
        $r->show_like_icon = $r->guest_id !== '38';

        $r->can_show_insight = false;
        if (
            !empty($member)
            && $member instanceof Cast
            && !empty($r->night_cast_id)
            && $r->night_cast_id == $member->id
        ) {
            $r->can_show_insight = true;
        }

        // LUM_ALL-1229 【夜遊び選手権】「ポストする・リンクをコピー」が404になっている投稿の処理を仕様変更
        // NOTE: ショコラ・ジョブショコラの投稿は「ポストする・リンクをコピー」が表示
        // NOTE: 夜遊びショコラの投稿ではキャスト投稿のみステータスを考慮し「ポストする・リンクをコピー」を表示
        // shop cast status
        $r->can_show_post_link = true;
        if (!empty($r->night_cast) && !empty($r->night_cast->shop)) {
            // night town cast
            $r->can_show_post_link = !empty($r->night_cast_detail_url) ? true : false;
        }

        return $r;
    }

    /**
     * Get the count of replies to a comment
     *
     * @param integer $tissueId The ID of the tissue.
     * @return integer
     */
    public function getCommentReplyCount(int $tissueId)
    {
        $query = TissueComment::leftJoin(
            'tissue_comment as master_tissue_comment',
            'master_tissue_comment.id',
            '=',
            'tissue_comment.master_comment_id'
        )
            ->where(function ($q) {
                $q->whereNull('master_tissue_comment.del')
                    ->orWhere(
                        'master_tissue_comment.del',
                        '=',
                        0
                    );
            })
            ->where('tissue_comment.master_comment_id', '=', $tissueId);
        return $query->notDeleted()->count();
    }

    /**
     * Get the count of comments that are not deleted and not written by the tissue author.
     *
     * @param array $tissueIds The IDs of the tissues.
     * @return Collection
     */
    public function getTissueCommentMasterCount(array $tissueIds)
    {
        $tissues = Tissue::select('id', 'cast_id', 'mypage_id', 'night_cast_id')->whereIn('id', $tissueIds)->get();

        $query = TissueComment::leftJoin(
            'tissue_comment as master_tissue_comment',
            'master_tissue_comment.id',
            '=',
            'tissue_comment.master_comment_id'
        )
            ->select('tissue_comment.tissue_id', DB::raw('COUNT(*) as count'))
            ->whereIn('tissue_comment.tissue_id', $tissueIds)
            ->where(function ($q) {
                $q->whereNull('master_tissue_comment.del')
                    ->orWhere('master_tissue_comment.del', '=', 0);
            })
            ->where(function ($q) {
                $q->whereNull('tissue_comment.master_comment_id')
                    ->orWhere(function ($q) {
                        $q->whereNotNull('tissue_comment.master_comment_id')
                            ->whereNotNull('master_tissue_comment.id');
                    });
            })
            ->whereRaw("IFNULL(tissue_comment.master_comment_id, 0) <> tissue_comment.id");

        $query->where(function($query) use ($tissues) {
            foreach ($tissues as $tissue) {
                $query->orWhere(function ($q) use ($tissue) {
                    $q->where('tissue_comment.tissue_id', $tissue->id)
                        ->where(function ($subQuery) use ($tissue) {
                            if (!is_null($tissue->cast_id)) {
                                $subQuery->whereRaw('NOT (tissue_comment.cast_id <=> ?)', [$tissue->cast_id]);
                            }
                            if (!is_null($tissue->mypage_id)) {
                                $subQuery->whereRaw('NOT (tissue_comment.mypage_id <=> ?)', [$tissue->mypage_id]);
                            }
                            if (!is_null($tissue->night_cast_id)) {
                                $subQuery->whereRaw('NOT (tissue_comment.night_cast_id <=> ?)', [$tissue->night_cast_id]);
                            }
                        }
                        );
                });
            }
        });

        $query->groupBy('tissue_comment.tissue_id');

        $results = $query->notDeleted()->get()->keyBy('tissue_id');

        $finalResult = [];
        foreach ($tissueIds as $tissueId) {
            $finalResult[$tissueId] = $results->has($tissueId) ? $results->get($tissueId)->count : 0;
        }

        return collect($finalResult);
    }

    /**
     * get member's comment
     * @param $member
     * @param string $createTimeLimit
     * @param bool $isSnsLogin
     * @param bool $distinctTissue
     * @param integer | array $tissueId
     * @return mixed
     */
    public function getCommentCountByMemberId(
        $member,
        $createTimeLimit = null,
        $isSnsLogin = false,
        $distinctTissue = false,
        $tissueId = 0
    ) {
        if ($member instanceof Cast) {
            $queryTissueComment = TissueComment::where('night_cast_id', '=', $member->id);
        } elseif ($member instanceof Member) {
            $queryTissueComment = TissueComment::where('night_mypage_id', '=', $member->id);
        } else {
            return 0;
        }

        if (!is_null($createTimeLimit)) {
            $queryTissueComment->where('created_at', '>=', $createTimeLimit);
        }

        if (true === $isSnsLogin) {
            $queryTissueComment->where('is_sns_login', $isSnsLogin);
        }

        if (is_array($tissueId)) {
            $queryTissueComment->whereIn('tissue_id', $tissueId);
        } elseif ($tissueId > 0) {
            $queryTissueComment->where('tissue_id', $tissueId);
        }

        if ($distinctTissue === true) {
            $queryTissueComment
                ->select('tissue_id')
                ->groupBy('tissue_id');

            return $queryTissueComment->get()->count();
        }

        return $queryTissueComment->count();
    }

    /**
     * Number format
     * @param integer $cnt
     * @return string
     */
    public function numberFormat($cnt)
    {
        if (0 >= $cnt) {
            return '0';
        }
        $base = 1;
        $thousand_limit = 1000;
        $million_limit = 1000000;

        if ($cnt >= ($million_limit * $base)) {
            $format = floor($cnt / $million_limit);
            $round = floor($cnt / $million_limit * 10) / 10;
            $suffix = 'M';
        } else {
            $base = 10;
            $format = floor($cnt / $thousand_limit);
            $round = floor($cnt / $thousand_limit * 10) / 10;
            $suffix = 'k';
        }

        $resp = $cnt;
        if ($format >= $base) {
            $resp = $round . $suffix;
        }

        return (string)$resp;
    }

    /**
     * Modify Num View
     * @param $tissue
     * @return string
     */
    public function countView($tissue)
    {
        $resp = $this->numberFormat($tissue->good_count + $tissue->add_good_count);

        return $resp;
    }

    /**
     * get tissue show image url
     *
     * @param null $oldMypageId
     * @param null $oldCastId
     * @param null $oldNightCastId
     * @param $castId
     * @param $guestId
     * @param $mypageId
     * @param $shopId
     * @param $nightCastId
     * @param $nightShopId
     * @param $imageFile
     * @param $thumbnailFile
     * @param $movieFile
     * @param bool $onlyPath
     * @param bool $isAutoGenerateGif
     * @return string
     */
    private function makeTissueImageUrl(
        $oldMypageId,
        $oldCastId,
        $oldNightCastId,
        $castId,
        $guestId,
        $mypageId,
        $shopId,
        $nightCastId,
        $nightShopId,
        $imageFile,
        $thumbnailFile,
        $movieFile,
        $onlyPath = false,
        $isAutoGenerateGif = false
    ) {
        $showImageFile = '';
        $awsURL = '';
        $path = '';

        if (!empty($imageFile)) {
            $awsURL = $this->twitterPath();
            $showImageFile = $imageFile;
        } elseif (!empty($thumbnailFile)) {
            $awsURL = $this->twitterPath();
            $showImageFile = $thumbnailFile;
            if (true === $isAutoGenerateGif) {
                $awsURL = $this->twitterMoivePath();
                $showImageFile = $thumbnailFile;
            }
        } elseif (!empty($movieFile)) {
            $awsURL = $this->twitterMoivePath();
            $imageName = explode('.', $movieFile)[0];
            $showImageFile = $imageName . self::THUMBNAIL_SECOND;
        }

        if (!empty($oldMypageId)) {
            $path = '/mypage/' . $oldMypageId . '/';
        } elseif (!empty($oldCastId)) {
            $path = '/cast/' . $oldCastId . '/';
        } elseif (!empty($oldNightCastId)) {
            $path = '/night-cast/' . $oldNightCastId . '/';
        } elseif (!empty($castId)) {
            $path = '/cast/' . $castId . '/';
        } elseif (!empty($guestId)) {
            $path = '/guest/' . $guestId . '/';
        } elseif (!empty($mypageId)) {
            $path = '/mypage/' . $mypageId . '/';
        } elseif (!empty($nightCastId)) {
            $path = '/night-cast/' . $nightCastId . '/';
        } elseif (!empty($nightShopId)) {
            $path = '/night-shop/' . $nightShopId . '/';
        } elseif (!empty($shopId)) {
            $path = '/' . $shopId . '/';
        }

        if (true === $onlyPath) {
            return $path;
        } else {
            return $awsURL . $path . $showImageFile;
        }
    }

    /**
     * tissueレコードに詳細情報の付与
     * @param $r
     * @param $rankingRows
     * @param $cookies
     * @param $nightShops
     * @param null $tissueCommentCountArr
     * @return mixed
     */
    public function attachDetailAttributesCombinedTissue(
        $member,
        $r,
        $rankingRows,
        $cookies,
        $nightShops,
        $nightCasts,
        $tissueCommentCountArr = null
    ) {
        if ($r->total_good_count <= 0) {
            $r->total_good_count = 0;
        }

        $r->ranking = '';

        // yoasobi shop ranking
        if ($r->tissue_type == self::TISSUE_TYPE_GIRL) {
            if (
                $r->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_CAST
                || $r->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_SHOP
            ) {
                $night_shop = $r->nightShopMains ?? $r->nightCastShopMains;

                if (
                    !empty($night_shop)
                    && $night_shop->plan > 5
                    && $night_shop->status_id ===  4
                ) {
                    $r->night_shop = $night_shop;
                    $prefectureId = $night_shop->prefecture_id;

                    if ($prefectureId) {
                        $r->prefecture = AreaPref::where('area_id', $prefectureId)->first();

                        $data = collect($rankingRows[$prefectureId] ?? [])->where('id', $night_shop->id);
                        if ($data->isNotEmpty()) {
                            $r->ranking = $data->keys()->first() + 1;
                        } else {
                            $r->ranking = '';
                        }
                    } else {
                        $r->ranking = '';
                    }
                }
            }
        }

        $r->comment_count = 0;
        $r->member_comment_exisits = false;

        if (is_null($tissueCommentCountArr)) {
            $r->comment_count = $this->getTissueCommentMaster(explode(',', $r->tissue_ids), [], 0, 0, 0, true, true);
            $r->comment_count = $r->comment_count->sum();
        } else {
            $tissueCommentCountArr = $tissueCommentCountArr[$r->tissue_type] ?? [];

            if (isset($tissueCommentCountArr[$r->id])) {
                $r->comment_count = $tissueCommentCountArr[$r->id];
                if (true === !empty($member) && $r->comment_count > 0) {
                    if ($r->tissue_type == self::TISSUE_TYPE_GIRL) {
                        $r->member_comment_exisits = $this->getCommentCountByMemberId($member, null, false, false,
                                $r->id) > 0;
                    } else {
                        $r->member_comment_exisits = 0;
                    }
                }
            }
        }
        $r->comment_count_format = (0 < $r->comment_count) ? $this->numberFormat($r->comment_count)
            : '';

        $r->countview = $this->countView($r);

        if ($r->image_url) {
            $filePath = self::makeTissueImageUrlCombinedTissue($r);
            if ($r->tissue_type == self::TISSUE_TYPE_MEN) {
                $r->image_url = $this->mensTwitterPath() . $filePath . $r->image_url_resize;
            } else {
                $r->image_url = $this->twitterPath() . $filePath . $r->image_url_resize;
            }
        } else {
            $r->image_url = '';// if $r->image_url = null, frontend maybe error;
        }

        if ($r->movie_url) {
            $filePath = self::makeTissueImageUrlCombinedTissue($r);

            $imageName = explode('.', $r->movie_url)[0];

            if ($r->tissue_type == self::TISSUE_TYPE_MEN) {
                $r->movie_image_url = $this->mensTwitterMoivePath() . $filePath . $imageName . self::THUMBNAIL_SECOND;
                $r->movie_thumbnail_url = (empty($r->thumbnail_url)) ? $r->movie_image_url : $this->mensTwitterPath() . $filePath . $r->thumbnail_url;
                $r->movie_url = $this->mensTwitterMoivePath() . $filePath . $r->movie_url;
            } else {
                $r->movie_image_url = $this->twitterMoivePath() . $filePath . $imageName . self::THUMBNAIL_SECOND;
                $r->movie_thumbnail_url = (empty($r->thumbnail_url)) ? $r->movie_image_url : $this->twitterPath() . $filePath . $r->thumbnail_url;
                $r->movie_url = $this->twitterMoivePath() . $filePath . $r->movie_url;
            }

        } else {
            $r->movie_url = ''; // if $r->movie_url = null, frontend maybe error;
        }

        $isAutoGenerateGif = false;
        if (!empty($r->image_attributes)) {
            $imageAttributes = json_decode($r->image_attributes, true);
            $isAutoGenerateGif = $imageAttributes['isAutoGenerateGif'] ?? false;
            if (!empty($imageAttributes['sticker'])) {
                $stickerFileType = (true === Agent::isMobile()) ? 'sp' : 'pc';
                $sticker = "/img/user/common/promotion/stickers/{$stickerFileType}/{$imageAttributes['sticker']}";
                $imageAttributes['sticker'] = secure_asset($sticker);
            }

            if (true === $isAutoGenerateGif) {
                if ($r->tissue_type == self::TISSUE_TYPE_GIRL) {
                    $r->movie_image_url = $this->twitterMoivePath() . $filePath . $r->thumbnail_url;
                } else {
                    $r->movie_image_url = $this->mensTwitterMoivePath() . $filePath . $r->thumbnail_url;
                }
                $r->movie_thumbnail_url = $r->movie_image_url;
            }

            $r->image_attributes = json_encode($imageAttributes);
        }

        //Shop Icon
        $shopIcon = '';

        if ($r->tissue_type == self::TISSUE_TYPE_GIRL) { // Girl Tissue
            if (
                $r->tissue_from_type == self::TISSUE_TYPE_GIRL_CAST
                || $r->tissue_from_type == self::TISSUE_TYPE_GIRL_SHOP
            ) {
                if ($r->tissue_from_type == self::TISSUE_TYPE_GIRL_CAST && $r->casts) {
                    $shopIcon = 'casts/' . $r->casts->cast_main_photo;
                } elseif ($r->shopMains && $r->shopMains->chocolatPic) {
                    $shopIcon = $r->shopMains->chocolatPic->main_file;
                    if (
                        $r->shopMains->pay_flg == 40
                        && !empty($r->shopMains->chocolatPic->splan_file_sp)
                    ) {
                        $shopIcon = $r->shopMains->chocolatPic->splan_file_sp;
                    } elseif (
                        (
                            $r->shopMains->pay_flg == 30
                            || $r->shopMains->pay_flg == 32
                        )
                        && !empty($r->shopMains->chocolatPic->aplan_file_sp)
                    ) {
                        $shopIcon = $r->shopMains->chocolatPic->aplan_file_sp;
                    }
                }

                $shopId = optional($r->shopMains)->id ?? optional($r->castShopMains)->id;
                $r->shop_icon = ($shopIcon && $shopId) ? $this->shopPath() . '/' . $shopId . '/' . $shopIcon : '';
            } elseif (
                $r->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_CAST
                || $r->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_SHOP
            ) {
                if ($r->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_SHOP) {
                    $r->shop_icon = !empty($r->nightShopMains->img_main_path) ?
                        $r->nightShopMains->real_img_main_path
                        : '';
                } else {
                    $nightCast = $nightCasts->firstWhere('id', $r->target_id);
                    $nightCastIcon = '';
                    if (!empty($nightCast) && !empty($nightCast->firstCastImage)) {
                        $nightCastIcon = $nightCast->firstCastImage->real_path;
                    }

                    if (!empty($r->nightCastShopMains) && empty($nightCastIcon)) {
                        $nightCastIcon = ($r->nightCastShopMains->img_main_path)
                            ? $r->nightCastShopMains->real_img_main_path
                            : '';
                    }

                    $r->shop_icon = !empty($nightCastIcon) ? $nightCastIcon : '';
                }
            }

            $r->shop_image = !empty($r->image_url) ? $r->image_url : '';
            $r->guest_icon = (!empty($r->guests) && !empty($r->guests->guest_photo)) ? $r->guests->real_image : '';
            $r->mypage_icon = (!empty($r->chocoMypage) && ($r->chocoMypage->profile_image))
                ? $r->chocoMypage->real_image
                : '';
        } else { // Men Tissue
            if ($r->tissue_from_type == self::TISSUE_TYPE_MEN_NIGHT_SHOP) {
                $r->shop_icon = !empty($r->nightShopMains->img_main_path)
                    ? $r->nightShopMains->real_img_main_path
                    : '';
            } else {
                $shopIcon = '';
                if ($r->staffs && $r->mensShopMains) {
                    if (
                        $r->mensShopMains->pay_flg > 8
                        && !empty($r->mensShopMains->mensChocolatPic->main_file2)
                    ) {
                        $shopIcon = "{$this->mensShopPath()}/{$r->mensShopMains->id}/"
                            . $r->mensShopMains->mensChocolatPic->main_file2;
                    } elseif (
                        $r->mensShopMains->pay_flg = 8
                            && !empty($r->mensShopMains->mensChocolatPic->main_file)
                    ) {
                        $shopIcon = "{$this->mensShopPath()}/{$r->mensShopMains->id}/"
                            . $r->mensShopMains->mensChocolatPic->main_file;
                    }
                }

                $r->shop_icon = (!empty($shopIcon)) ? $shopIcon : '';
            }

            $r->shop_image = !empty($r->image_url) ? $r->image_url : '';
            $r->guest_icon = !empty($r->mensGuests) ? $r->mensGuests->real_image : '';
            $r->mypage_icon = '';
            if (!empty($r->mensChocoMypage)) {
                $r->mypage_icon = (!empty($r->mensChocoMypage->profile_image))
                    ? $this->mensChocoMypagePath() . '/' . $r->target_id . '/' . $r->mensChocoMypage->profile_image
                    : '';
            }

            if ($r->tissue_from_type == self::TISSUE_TYPE_MEN_NIGHT_MYPAGE) {
                $r->night_mypage_icon = !empty($r->nightChocoMypage->img_plofile_path)
                    ? $r->nightChocoMypage->real_img_plofile_path
                    : '';
            }
        }

        if (!empty($member)) {
            $this->likeCount = 20;
        }

        if ($cookies[$r->tissue_type] && array_key_exists($r->id, $cookies[$r->tissue_type])) {
            $r->liked_before = true;
            if ($cookies[$r->tissue_type][$r->id] >= $this->likeCount) {
                $r->liked = true;
            } else {
                $r->liked = false;
            }
        } else {
            $r->liked_before = false;
        }

        // 公式アカウントの場合、イイネできない様にする。暫定対応
        $r->show_like_icon = ($r->tissue_from_type == self::TISSUE_TYPE_GIRL_GUEST && $r->target_id == '38')
            ? false : true;

        $r->show_sns_icon = false;
        if (!empty($r->sns_count)) {
            $r->show_sns_icon = true;
        }

        // LUM_ALL-1229 【夜遊び選手権】「ポストする・リンクをコピー」が404になっている投稿の処理を仕様変更
        // NOTE: ショコラ・ジョブショコラの投稿は「ポストする・リンクをコピー」が表示
        // NOTE: 夜遊びショコラの投稿ではキャスト投稿のみステータスを考慮し「ポストする・リンクをコピー」を表示
        // shop cast status
        $r->can_show_post_link = true;
        if ($r->tissue_type == self::TISSUE_TYPE_GIRL) {
            if ($r->tissue_from_type == self::TISSUE_TYPE_GIRL_CAST) {
                // ショコラキャスト投稿　夜遊びキャストと紐づけがある場合
                if (!empty($r->casts->nightCast)) {
                    if (
                        $r->casts->nightCast->shop->status_id === 4
                        && $r->casts->nightCast->status_id === 4
                    ) {
                        // 紐づけ先の夜遊び店舗と夜遊びキャストが表示ステータスの場合
                        $r->can_show_post_link = true;
                    } else {
                        $r->can_show_post_link = false;
                    }
                }
            } elseif ($r->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_CAST) {
                // night town cast
                $night_cast = $nightCasts->firstWhere('id', $r->target_id);
                if (
                    !empty($night_cast)
                    && $night_cast->status_id === 4
                    && !empty($r->nightCastShopMains)
                    && $r->nightCastShopMains->status_id === 4
                    && $r->nightCastShopMains->is_closed === false
                ) {
                    $r->can_show_post_link = true;
                } else {
                    $r->can_show_post_link = false;
                }
            }
        }

        return $r;
    }

    /**
     * get tissue show image url
     * @param $tissue
     * @return string
     */
    private function makeTissueImageUrlCombinedTissue($tissue)
    {
        $path = '';

        if ($tissue->tissue_from_type == self::TISSUE_TYPE_GIRL_CAST) {
            $path = '/cast/' . $tissue->target_id . '/';
        } elseif ($tissue->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_CAST) {
            $path = '/night-cast/' . $tissue->target_id . '/';
        } elseif (
            $tissue->tissue_from_type == self::TISSUE_TYPE_GIRL_GUEST
            || $tissue->tissue_from_type == self::TISSUE_TYPE_MEN_GUEST
        ) {
            $path = '/guest/' . $tissue->target_id . '/';
        } elseif (
            $tissue->tissue_from_type == self::TISSUE_TYPE_GIRL_MYPAGE
            || $tissue->tissue_from_type == self::TISSUE_TYPE_MEN_MYPAGE
        ) {
            $path = '/mypage/' . $tissue->target_id . '/';
        } elseif (
            $tissue->tissue_from_type == self::TISSUE_TYPE_GIRL_SHOP
            || $tissue->tissue_from_type == self::TISSUE_TYPE_MEN_SHOP
        ) {
            $path = '/' . $tissue->shopMains->getOriginal('shop_id') . '/';
        } elseif ($tissue->tissue_from_type == self::TISSUE_TYPE_MEN_STAFF) {
            $path = '/staff/' . $tissue->target_id . '/';
        } elseif (
            $tissue->tissue_from_type == self::TISSUE_TYPE_MEN_NIGHT_SHOP
            || $tissue->tissue_from_type == self::TISSUE_TYPE_GIRL_NIGHT_SHOP
        ) {
            $path = '/night-shop/' . $tissue->target_id . '/';
        } elseif ($tissue->tissue_from_type == self::TISSUE_TYPE_MEN_NIGHT_MYPAGE) {
            $path = '/night-mypage/' . $tissue->target_id . '/';
        }

        if (!empty($tissue->old_mypage_id)) {
            $path = '/mypage/' . $tissue->old_mypage_id . '/';
        } elseif (!empty($tissue->old_cast_id)) {
            $path = '/cast/' . $tissue->old_cast_id . '/';
        } elseif (!empty($tissue->old_night_cast_id)) {
            $path = '/night-cast/' . $tissue->old_night_cast_id . '/';
        } elseif (!empty($tissue->old_staff_id)) {
            $path = '/staff/' . $tissue->old_staff_id . '/';
        }

        return $path;
    }

    /**
     * Get mens tissue image path
     * @return mixed
     */
    public function mensTwitterPath()
    {
        if (App::environment() === 'production') {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN_RELEASE') . '/mens/twitter';
        } else {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN') . '/mens/twitter';
        }
    }

    /**
     * Get tissue movie path
     * @return mixed
     */
    public function mensTwitterMoivePath()
    {
        if (App::environment() === 'production') {
            return 'https://s3-ap-northeast-1.amazonaws.com/encodefiles.chocolat.work/mens/twitter';
        } else {
            return 'https://s3-ap-northeast-1.amazonaws.com/encodedevfiles.chocolat.work/mens/twitter';
        }
    }

    /**
     * Get job shop and cast header image path
     * @return mixed
     */
    public function mensShopPath()
    {
        if (App::environment() === 'production') {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN_RELEASE') . '/mens/shop/img';
        } else {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN') . '/mens/shop/img';
        }
    }

    /**
     * Get job mypage header image path
     * @return mixed
     */
    public function mensChocoMypagePath()
    {
        if (App::environment() === 'production') {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN_RELEASE') . '/mens/mypage/img';
        } else {
            return 'https://' . env('AWS_CF_CAMPIONSHIP_DOMAIN') . '/mens/mypage/img';
        }
    }
}
