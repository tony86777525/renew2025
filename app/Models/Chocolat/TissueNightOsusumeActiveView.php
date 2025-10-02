<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class TissueNightOsusumeActiveView extends Model
{
    protected $table = 'tissue_night_osusume_task_active_view';

    /**
     * 開放程式碼新增或編輯的欄位
     * 避免誤改其他欄位
     */
    protected $fillable = [
        'tissue_id',
        'target_id',
        'tissue_type',
        'tissue_from_type',
        'old_mypage_id',
        'old_cast_id',
        'old_night_cast_id',
        'old_staff_id',
        'pref_id',
        'recommend_flg',
        // 累計分數。
        // 被喜歡次數。
        'good_count',
        // 被喜歡次數-手動灌水。
        'add_good_count',
        // 被喜歡次數-自動灌水。
        'auto_add_good_count',
        // 被瀏覽到的次數。
        'view_count',
        // SNS分享次數-由外部定期匯入。
        'sns_count',
        // 影片類型投搞的播放次數。
        'movie_view_count',
        // 投稿類型。
        // 圖片。
        'image_url',
        // 影片。
        'movie_url',
        // 自訂影片預覽圖，以前可以自訂上傳，現在則是有自動生成影片預覽圖，不再讓使用者上傳。
        'thumbnail_url',
        // 浮水印設定。
        'image_attributes',
        // 是否為前台投稿。0:後台投稿, 1:前台投稿
        'mypage_post_flg',
        // 自訂輸入文字。
        'caption',
        // 業務用，是否已聯絡投稿者。
        'has_contacted',
        // 狀態開關。
        'published_flg',
        // 0:on Apply, 1:normal
        'apply_flg',
        // TOP畫像設定。
        'set_top_status',
        // 營業投稿用的欄位，追蹤績效。
        // 営業投稿承認時間，由前台發布的營業投稿，需後台許可。
        'approved_date',
        // 営業投稿承認狀態，基本上都會是許可狀態。0:delete,
        // 1:normal, 2:on apply, 3:deny
        'tissue_status',
        // 営業投稿的建立者所屬營業支社。
        'sale_branch_id',
        // 営業投稿的建立者。
        'sale_id',
        // 営業投稿的建立者。
        'sale_shop_id',
        // 投稿女生。
        'sale_cast_name',
        // 投稿設定後會優先顯示於前台論播中。
        'slider_num',
        // 前台公開顯示時間。
        'release_date',
        // 新增資料時間。
        'created_at',
        // 更新資料時間。
        'updated_at',
        // 原本是紀錄SNS的分享連結，沒在用了。
        // 'link_url',
        // 應該沒在使用了
        // 'order_num',
    ];

    /**
     * Constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->connection = env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat');
    }

    public function scopePublished($query)
    {
        return $query->where($this->table . '.published_flg', 1);
    }

    public function scopeTissueStatusActive($query)
    {
        return $query->where($this->table . '.tissue_status', 1);
    }
}
