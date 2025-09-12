<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class Guest extends Model
{
    protected $table = 'tissueguests';

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

    public function getDisplayPhotoPathAttribute() : string
    {
        $url = ('production' === App::environment())
            ? Config::get('myapp.chocolat_twitter_path.production')
            : Config::get('myapp.chocolat_twitter_path.dev');

        return !empty($this->guest_photo)
            ? "{$url}/guest/{$this->guest_photo}?o=webp&type=resize&width=1000&height=1000&quality=95"
            : '';
    }
}
