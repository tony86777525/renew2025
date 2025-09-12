<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class MypageMain extends Model
{
    protected $table = 'mypage_mains';

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

    public function mypage()
    {
        return $this->belongsTo('App\Models\Chocolat\Mypage', 'id', 'id');
    }

    public function getDisplayNameAttribute() : string
    {
        return !empty($this->nickname) ? $this->nickname:'userC'.substr(md5($this->id), 0, 6);
    }

    /**
     * LUM_ALL-18 会員詳細ページ choco mypage profile Image
     * @return string
     */
    public function getDisplayPhotoPathAttribute() : string
    {
        $chocoUrl = ('production' === App::environment())
            ? Config::get('myapp.chocolat_mypage_path.production')
            : Config::get('myapp.chocolat_mypage_path.dev');

        return !empty($this->profile_image)
            ? "{$chocoUrl}/{$this->id}/{$this->profile_image}?o=webp&type=resize&width=1000&height=1000&quality=95"
            : '';
    }
}
