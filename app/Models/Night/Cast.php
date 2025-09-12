<?php

namespace App\Models\Night;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

        $this->connection = env('DB_CONNECTION', 'mysql');
    }

    public function shop()
    {
        return $this->belongsTo('App\Models\Night\Shop');
    }

    public function firstCastImage()
    {
        return $this->hasOne('App\Models\Night\CastImage')->orderBy('sort_number');
    }

    public function chocolatCast()
    {
        return $this
            ->setConnection(env('DB_CHOCOLAT_CONNECTION', 'mysql-chocolat'))
            ->hasOne('App\Models\Chocolat\Cast', 'town_night_cast_id', 'id');
    }

    public function getDisplayNameAttribute() : string
    {
        return !empty($this->name)
            ? $this->name
            : 'userC' . substr(md5($this->id), 0, 6);
    }

    public function getDisplayPhotoPathAttribute() : string
    {
        $showImage = $this->firstCastImage;

        if (empty($showImage) || empty($showImage->path)) {
            return '';
        }

        $imagePath = $showImage->path;

        $path = Storage::disk(config('myapp.storage_disk'))->url($imagePath);

        return config('myapp.storage_disk') === 's3' ? s3_2_cf($path) : $path;
    }
}
