<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class MensTissue extends Model
{
    protected $table = 'mens_tissue';

    CONST THUMBNAIL_SECOND = '-00003.png';

    /**
     * Tissue 來源類型常數
     */
    const TISSUE_FROM_TYPE_MEN_MYPAGE = 'MEN_MYPAGE';
    const TISSUE_FROM_TYPE_MEN_GUEST = 'MEN_GUEST';
    const TISSUE_FROM_TYPE_MEN_STAFF = 'MEN_STAFF';
    const TISSUE_FROM_TYPE_MEN_NIGHT_MYPAGE = 'MEN_NIGHT_MYPAGE';
    const TISSUE_FROM_TYPE_MEN_SHOP = 'MEN_SHOP';
    const TISSUE_FROM_TYPE_MEN_NIGHT_SHOP = 'MEN_NIGHT_SHOP';

    /**
     * 開放程式碼新增或編輯的欄位
     * 避免誤改其他欄位
     */
    protected $fillable = [
        // 投稿類型。
        // JobShop投稿，mens_shop_mains.shop_id。
        'shop_id',
        // JobStaff投稿，mens_staffs.id。
        'staff_id',
        // JobMypage投稿，mens_mypage_mains.id。
        'mypage_id',
        // NightShop投稿，yoasobi_shops_all.id。
        'night_shop_id',
        // NightMypage投稿，yoasobi_members.id。
        'night_mypage_id',
        // Job特別來賓投稿，guests.id。
        'guest_id',
        // 轉移投稿時，紀錄投稿的來源，用於追朔投稿檔案存放位置(AWS資料夾)。
        // 原本從JobMypage會員新增的投稿。
        'old_mypage_id',
        // 原本從JobStaff投稿的投稿。
        'old_staff_id',
        // 可能有在用，在前台顯示夜遊店鋪網址。
        'town_night_shop_url',
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
        // 営業投稿男生的店鋪。
        'sale_shop_id',
        // 営業投稿的男生。
        'sale_staff_name',
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

    /**
     * get image_url with cloud front query string
     * @return string
     */
    public function getImageUrlResizeAttribute()
    {
        return $this->image_url . '?o=webp&type=resize&width=1000&height=1000&quality=95';
    }

    public function getFrontShowImagePathAttribute()
    {
        $imageAttributes = json_decode($this->image_attributes, true);
        $isAutoGenerateGif = $imageAttributes['isAutoGenerateGif'] ?? false;

        $baseUrl = 'https://d1wkjlhn9f2agy.cloudfront.net/mens/twitter';
        if (!empty($this->movie_url) || (!empty($this->thumbnail_url) && $isAutoGenerateGif === true)) {
            $baseUrl = 'https://s3-ap-northeast-1.amazonaws.com/encodedevfiles.chocolat.work/mens/twitter';
        }

        $fileName = '';
        if (!empty($this->image_url)) {
            $fileName = $this->image_url_resize;
        } elseif (!empty($this->original['thumbnail_url'])) {
            $fileName = $this->original['thumbnail_url'];
        } elseif (!empty($this->original['movie_url'])) {
            $imageName = explode('.', $this->original['movie_url'])[0];
            $fileName = $imageName . self::THUMBNAIL_SECOND;
        }

        $filePath = '';
        switch (true) {
            case !empty($this->old_mypage_id):
                $filePath = "mypage/{$this->old_mypage_id}";
                break;
            case !empty($this->old_staff_id):
                $filePath = "staff/{$this->old_staff_id}";
                break;
            case !empty($this->mypage_id):
                $filePath = "mypage/{$this->mypage_id}";
                break;
            case !empty($this->staff_id):
                $filePath = "staff/{$this->staff_id}";
                break;
            case !empty($this->night_mypage_id):
                $filePath = "night-mypage/{$this->night_mypage_id}";
                break;
            case !empty($this->guest_id):
                $filePath = "guest/{$this->guest_id}";
                break;
            case !empty($this->shop_id):
                $filePath = "{$this->shop_id}";
                break;
            case !empty($this->night_shop_id):
                $filePath = "night-shop/{$this->night_shop_id}";
                break;
        }

        return "{$baseUrl}/{$filePath}/{$fileName}";
    }

    public function getUserTypeAttribute()
    {
        if (!empty($this->mypage_id)) {
            return self::TISSUE_FROM_TYPE_MEN_MYPAGE;
        }

        if (!empty($this->staff_id)) {
            return self::TISSUE_FROM_TYPE_MEN_STAFF;
        }

        if (!empty($this->night_mypage_id)) {
            return self::TISSUE_FROM_TYPE_MEN_NIGHT_MYPAGE;
        }

        return self::TISSUE_FROM_TYPE_MEN_GUEST;
    }

    public function getUserIdAttribute()
    {
        if (!empty($this->mypage_id)) {
            return $this->mypage_id;
        }

        if (!empty($this->staff_id)) {
            return $this->staff_id;
        }

        if (!empty($this->night_mypage_id)) {
            return$this->night_mypage_id;
        }

        return $this->guest_id;
    }
}
