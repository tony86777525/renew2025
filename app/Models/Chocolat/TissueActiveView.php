<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class TissueActiveView extends Model
{
    protected $table = 'tissue_active_view';

    CONST THUMBNAIL_SECOND = '-00003.png';

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
        // 營業投稿用的欄位，追蹤績效。
        // 営業投稿承認時間，由前台發布的營業投稿，需後台許可。
        'approved_date',
        // 営業投稿承認狀態，基本上都會是許可狀態。0:delete,
        // 1:normal, 2:on apply, 3:deny
        'tissue_status',
        // 営業投稿的建立者。
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
        // 'town_night_shop_url',
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

    public function shopMains()
    {
        return $this->hasOne('App\Models\Chocolat\ShopMain', 'shop_id', 'target_id');
    }

    public function castShopMains()
    {
        return $this->hasOneThrough('App\Models\Chocolat\ShopMain','App\Models\Chocolat\Cast', 'id', 'id', 'target_id', 'shop_table_id');
    }

    public function nightCastShopMains()
    {
        return $this->hasOneThrough('App\Models\Night\Shop', 'App\Models\Night\Cast', 'id', 'id', 'target_id', 'shop_id');
    }

    /**
     * Get night shop mains by tissue model
     *
     * @return \App\Models\Shop|null
     */
    public function getNightShopMains()
    {
        $nightShopMains = null;

        if ($this instanceof CombinedTissue || $this instanceof CombinedTissueOsusume) {
            switch ($this->tissue_from_type) {
                case 'GIRL_NIGHT_CAST':
                    $nightShopMains = $this->nightCastShopMains;
                    break;
                case 'GIRL_NIGHT_SHOP':
                case 'MEN_NIGHT_SHOP':
                    $nightShopMains = $this->nightShopMains;
                    break;
                case 'GIRL_CAST':
                    $nightShopMains = $this->castShopMains->nightTownShop;
                    break;
                case 'GIRL_SHOP':
                    $nightShopMains = $this->shopMains->nightTownShop;
                    break;
                case 'MEN_STAFF':
                    $nightShopMains = $this->staffShopMains->chocolatShopMain->nightTownShop;
                    break;
                case 'MEN_SHOP':
                    $nightShopMains = $this->mensShopMains->chocolatShopMain->nightTownShop;
                    break;
                default:
                    break;
            }
        } elseif ($this instanceof Tissue) {
            // for ranking pages
            if (isset($this->poster)) {
                if ($this->poster == 'shop') {
                    $nightShopMains = $this->shopMains->nightTownShop;
                } elseif ($this->poster == 'cast') {
                    $nightShopMains = $this->castShopMains->nightTownShop;
                } elseif ($this->poster == 'night-shop') {
                    $nightShopMains = $this->nightShopMains;
                } elseif ($this->poster == 'night-cast') {
                    $nightShopMains = $this->nightCastShopMains;
                }
            } else {
                if (!empty($this->shop_id)) {
                    $nightShopMains = $this->shopMains->nightTownShop;
                } elseif (!empty($this->cast_id)) {
                    $nightShopMains = $this->castShopMains->nightTownShop;
                } elseif (!empty($this->night_shop_id)) {
                    $nightShopMains = $this->nightShopMains;
                } elseif (!empty($this->night_cast_id)) {
                    $nightShopMains = $this->nightCastShopMains;
                }
            }
        } elseif (1) {
            if (!empty($this->shop_id)) {
                $nightShopMains = $this->shopMains->chocolatShopMain->nightTownShop;
            } elseif (!empty($this->staff_id)) {
                $nightShopMains = $this->staffShopMains->chocolatShopMain->nightTownShop;
            } elseif (!empty($this->night_shop_id)) {
                $nightShopMains = $this->nightShopMains;
            }
        }

        return $nightShopMains;
    }

    public function scopePublished($query)
    {
        return $query->where($this->table . '.published_flg', 1);
    }

    public function scopeTissueStatusActive($query)
    {
        return $query->where($this->table . '.tissue_status', 1);
    }

    /**
     * get image_url with cloud front query string
     * @return string
     */
    public function getImageUrlResizeAttribute()
    {
        return $this->original['image_url'] . '?o=webp&type=resize&width=1000&height=1000&quality=95';
    }

    public function getFrontShowImagePathAttribute()
    {
        $imageAttributes = json_decode($this->original['image_attributes'], true);
        $isAutoGenerateGif = $imageAttributes['isAutoGenerateGif'] ?? false;

        $baseUrl = 'https://d1wkjlhn9f2agy.cloudfront.net/twitter';
        if (!empty($this->original['movie_url']) || (!empty($this->original['thumbnail_url']) && $isAutoGenerateGif === true)) {
            $baseUrl = 'https://s3-ap-northeast-1.amazonaws.com/encodedevfiles.chocolat.work/twitter';
        }

        $fileName = '';
        if (!empty($this->original['image_url'])) {
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
            case $this->tissue_from_type == 'GIRL_MYPAGE':
                $filePath = "mypage/{$this->target_id}";
                break;
            case $this->tissue_from_type == 'GIRL_CAST':
                $filePath = "cast/{$this->target_id}";
                break;
            case $this->tissue_from_type == 'GIRL_NIGHT_CAST':
                $filePath = "night-cast/{$this->target_id}";
                break;
            case $this->tissue_from_type == 'GIRL_GUEST':
                $filePath = "guest/{$this->target_id}";
                break;
            case $this->tissue_from_type == 'GIRL_SHOP':
                $filePath = "{$this->target_id}";
                break;
            case $this->tissue_from_type == 'GIRL_NIGHT_SHOP':
                $filePath = "night-shop/{$this->target_id}";
                break;
            case $this->tissue_from_type == 'MEN_NIGHT_SHOP':
                $filePath = "night-shop/{$this->target_id}";
                break;
        }

        return "{$baseUrl}/{$filePath}/{$fileName}";
    }
}
