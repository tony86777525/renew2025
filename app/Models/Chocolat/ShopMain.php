<?php

namespace App\Models\Chocolat;

use App\Facades\Taxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class ShopMain extends Model
{
    protected $table = 'shop_mains';

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

    public function area()
    {
        return $this->hasOne('App\Models\Chocolat\AreaMain', 'area_id', 'main_area');
    }

    public function prefecture()
    {
        return $this->hasOne('App\Models\Chocolat\AreaPref','area_id', 'pref_id');
    }

    public function genre()
    {
        return $this->hasOne('App\Models\Chocolat\GenreMain', 'genre_id', 'genre');
    }

    public function mensShopMain()
    {
        return $this->hasOne('App\Models\Chocolat\MensShopMain', 'choco_id', 'id');
    }

    public function nightShop()
    {
        return $this
            ->setConnection(env('DB_CONNECTION', 'mysql-slave'))
            ->hasOne('App\Models\Night\Shop', 'id', 'night_town_id');
    }

    /**
     * ショコラ店舗名アクセサ
     *
     * @return array|string|string[]|null
     */
    public function getDisplayNameAttribute()
    {
        $bracketRegEx = "/【[^】]+】/u";

        return preg_replace($bracketRegEx, '', $this->shop_name);
    }

    // ショコラ店舗詳細ページURL
    public function getDisplayUrlAttribute()
    {
        $chocoUrl = (App::environment() === 'production')
            ? 'https://chocolat.work/'
            : 'https://dev.chocolat.work/';

        return $chocoUrl . Taxonomy::getPrefectureById($this->pref_id)->name_alpha
            . '/a_' . $this->main_area . '/shop/' . $this->getOriginal('shop_id') . '/';
    }

    public function getDisplayGenreNameAttribute()
    {
        //北海道のキャバクラ、昼キャバ・朝キャバ、姉キャバ・半熟キャバ、熟女キャバクラの名称変更
        if (1 === $this->pref_id && !empty(config('myapp.hokkaido_genre_name')[$this->genre])) {
            return $this->genre->genre_name_sp;
        } else {
            return $this->genre->genre_name;
        }
    }

    public function getDisplayFullAddressAttribute()
    {
        if (empty($this->address2)) {
            return '';
        }

        return $this->prefecture->area_name3 . $this->address_city . $this->address2 . ' ' . $this->address3;
    }
}
