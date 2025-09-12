<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class Cast extends Model
{
    protected $table = 'casts';

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

    public function shopMain()
    {
        return $this->hasOne('App\Models\Chocolat\ShopMain', 'id', 'shop_table_id');
    }

    public function castMypage()
    {
        return $this->hasOne('App\Models\Chocolat\CastMypage', 'id', 'id');
    }

    public function nightCast()
    {
        return $this
            ->setConnection(env('DB_CONNECTION', 'mysql-slave'))
            ->hasOne('App\Models\Night\Cast', 'id', 'town_night_cast_id');
    }

    /**
     * Get the related ChocolatMypage record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough
     */
    public function mypage()
    {
        return $this->hasOneThrough(
            'App\Models\Chocolat\Mypage',
            'App\Models\Chocolat\CastMypageBind',
            'cast_id',
            'id',
            'id',
            'mypage_id'
        );
    }

    /**
     * get cast name
     * @return string
     */
    public function getDisplayNameAttribute() : string
    {
        return !empty($this->cast_name)
            ? $this->cast_name
            : 'userC' . substr(md5($this->id), 0, 6);
    }

    /**
     * Get cast main_photo by casts and shop_mains.
     *
     * @return string
     */
    public function getDisplayPhotoPathAttribute(): ?string
    {
        // PHP 8.0
//        match ($this->main_photo) {
//            1 => $this->cast_photo1,
//            2 => $this->cast_photo2,
//            3 => $this->cast_photo3,
//            4 => $this->cast_photo4,
//            5 => $this->cast_photo5,
//            6 => $this->cast_photo6,
//            7 => $this->cast_photo7,
//            8 => $this->cast_photo8,
//            9 => $this->cast_photo9,
//            10 => $this->cast_photo10,
//            default => $this->cast_photo
//        };

        if (App::environment() === 'production') {
            $url = Config::get('myapp.chocolat_shop_path.production');
        } else {
            $url = Config::get('myapp.chocolat_shop_path.dev');
        }

        switch ($this->main_photo) {
            case 1:
                $photo = $this->cast_photo1;
                break;
            case 2:
                $photo = $this->cast_photo2;
                break;
            case 3:
                $photo = $this->cast_photo3;
                break;
            case 4:
                $photo = $this->cast_photo4;
                break;
            case 5:
                $photo = $this->cast_photo5;
                break;
            case 6:
                $photo = $this->cast_photo6;
                break;
            case 7:
                $photo = $this->cast_photo7;
                break;
            case 8:
                $photo = $this->cast_photo8;
                break;
            case 9:
                $photo = $this->cast_photo9;
                break;
            case 10:
                $photo = $this->cast_photo10;
                break;
            default:
                $photo = $this->cast_photo;
        }

        return "{$url}/{$this->shopMain->id}/casts/{$photo}?o=webp&type=resize&width=1000&height=1000&quality=95";
    }
}
