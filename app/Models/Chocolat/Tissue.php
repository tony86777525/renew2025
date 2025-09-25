<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class Tissue extends Model
{
    protected $table = 'tissue';

    /**
     * Tissue 類型常數
     */
    const TISSUE_TYPE_GIRL = 'GIRL';
    const TISSUE_TYPE_MEN = 'MEN';

    /**
     * Tissue 來源類型常數
     */
    const TISSUE_FROM_TYPE_GIRL_MYPAGE = 'GIRL_MYPAGE';
    const TISSUE_FROM_TYPE_GIRL_GUEST = 'GIRL_GUEST';
    const TISSUE_FROM_TYPE_GIRL_CAST = 'GIRL_CAST';
    const TISSUE_FROM_TYPE_GIRL_NIGHT_CAST = 'GIRL_NIGHT_CAST';
    const TISSUE_FROM_TYPE_GIRL_SHOP = 'GIRL_SHOP';
    const TISSUE_FROM_TYPE_GIRL_NIGHT_SHOP = 'GIRL_NIGHT_SHOP';
    const TISSUE_FROM_TYPE_MEN_CAST = 'MEN_CAST';
    const TISSUE_FROM_TYPE_MEN_GUEST = 'MEN_GUEST';

    /**
     * 狀態常數
     */
    const TISSUE_STATUS_DELETE = 0;
    const TISSUE_STATUS_NORMAL = 1;
    const TISSUE_STATUS_ON_APPLY = 2;
    const TISSUE_STATUS_DENY = 3;

    const PUBLISHED_FLG_FALSE = 0;
    const PUBLISHED_FLG_TRUE = 1;

    CONST THUMBNAIL_SECOND = '-00003.png';

    /**
     * 開放程式碼新增或編輯的欄位
     * 避免誤改其他欄位
     */
    protected $fillable = [
        // 投稿類型。
        // ChocoShop投稿，shop_mains.shop_id。
        'shop_id',
        // ChocoCast會員投稿，casts.id。
        'cast_id',
        // ChocoMypage會員投稿，mypage_mains.id。
        'mypage_id',
        // NightShop店鋪投稿，shops.id。
        'night_shop_id',
        // NightCast投稿，casts.id。
        'night_cast_id',
        // Choco特別來賓投稿，guests.id。
        'guest_id',
        // 轉移投稿時，紀錄投稿的來源，用於追朔投稿檔案存放位置(AWS資料夾)。
        // 原本從ChocoMypage會員新增的投稿。
        'old_mypage_id',
        // 原本從ChocoCast會員新增的投稿。
        'old_cast_id',
        // 原本從NightCast會員新增的投稿。
        'old_night_cast_id',
        // 可能有在用，在前台顯示夜遊店鋪網址。
        'town_night_shop_url',
        // 手動綁定
        // CHOCO女生綁定夜遊女生的關聯欄位，設定其中一篇投稿，會被連動全部同投稿者的投稿資料。
        'town_night_cast_id',
        // 夜遊女生綁定CHOCO女生的關聯欄位，設定其中一篇投稿，會被連動全部同投稿者的投稿資料。
        'choco_cast_id',
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

    public function shop()
    {
        return $this->hasOne('App\Models\Chocolat\ShopMain', 'shop_id', 'shop_id');
    }

    public function mypage()
    {
        return $this->hasOne('App\Models\Chocolat\MypageMain', 'id', 'mypage_id');
    }

    public function cast()
    {
        return $this->hasOne('App\Models\Chocolat\Cast', 'id', 'cast_id');
    }

    public function guest()
    {
        return $this->hasOne('App\Models\Chocolat\Guest', 'id', 'guest_id');
    }

    public function nightShop()
    {
        return $this
            ->setConnection(env('DB_CONNECTION', 'mysql-slave'))
            ->hasOne('App\Models\Night\Shop', 'id', 'night_shop_id');
    }

    public function nightCast()
    {
        return $this
            ->setConnection(env('DB_CONNECTION', 'mysql-slave'))
            ->hasOne('App\Models\Night\Cast', 'id', 'night_cast_id');
    }

    public function hashTags()
    {
        return $this->hasMany('App\Models\Chocolat\HashTag', 'tissue_hashtags', 'tissue_id', 'hashtag_id');
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

        $baseUrl = 'https://d1wkjlhn9f2agy.cloudfront.net/twitter';
        if (!empty($this->movie_url) || (!empty($this->thumbnail_url) && $isAutoGenerateGif === true)) {
            $baseUrl = 'https://s3-ap-northeast-1.amazonaws.com/encodedevfiles.chocolat.work/twitter';
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
            case !empty($this->old_cast_id):
                $filePath = "cast/{$this->old_cast_id}";
                break;
            case !empty($this->old_night_cast_id):
                $filePath = "night-cast/{$this->old_night_cast_id}";
                break;
            case !empty($this->mypage_id):
                $filePath = "mypage/{$this->mypage_id}";
                break;
            case !empty($this->cast_id):
                $filePath = "cast/{$this->cast_id}";
                break;
            case !empty($this->night_cast_id):
                $filePath = "night-cast/{$this->night_cast_id}";
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
            return self::TISSUE_FROM_TYPE_GIRL_MYPAGE;
        }

        if (!empty($this->cast_id)) {
            return self::TISSUE_FROM_TYPE_GIRL_CAST;
        }

        if (!empty($this->night_cast_id)) {
            return self::TISSUE_FROM_TYPE_GIRL_NIGHT_CAST;
        }

        return self::TISSUE_FROM_TYPE_GIRL_GUEST;
    }

    public function getUserIdAttribute()
    {
        if (!empty($this->mypage_id)) {
            return $this->mypage_id;
        }

        if (!empty($this->cast_id)) {
            return $this->cast_id;
        }

        if (!empty($this->night_cast_id)) {
            return$this->night_cast_id;
        }

        return $this->guest_id;
    }
}
