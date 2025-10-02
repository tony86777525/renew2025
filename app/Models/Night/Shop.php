<?php

namespace App\Models\Night;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Shop extends Model
{
    protected $table = 'shops';

    /**
     * Constructor
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->connection = env('DB_CONNECTION', 'mysql');
    }

    public function prefecture()
    {
        return $this->belongsTo('App\Models\Night\Prefecture');
    }

    public function area()
    {
        return $this->belongsTo('App\Models\Night\Area');
    }

    public function genre()
    {
        return $this->belongsTo('App\Models\Night\Genre');
    }

    /**
     * @return string
     */
    public function getDisplayGenreNameAttribute()
    {
        //北海道のキャバクラ、昼キャバ・朝キャバ、姉キャバ・半熟キャバ、熟女キャバクラの名称変更
        if (1 === $this->prefecture_id && !empty(config('myapp.hokkaido_genre_name')[$this->genre])) {
            return $this->genre->genre_name_sp ?? '';
        } else {
            return $this->genre->genre_name ?? '';
        }
    }

    public function getDisplayPhotoPathAttribute() : string
    {
        if (empty($this->img_main_path)) return '';

        $path = Storage::disk(config('myapp.storage_disk'))->url($this->img_main_path);

        return config('myapp.storage_disk') === 's3' ? s3_2_cf($path) : $path;
    }

    /**
     * status_id が 「公開済み」の店舗だけに限定するクエリスコープ
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('shops.status_id', 4);
    }
}
