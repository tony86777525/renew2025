<?php

namespace App\Models\Night;

use Illuminate\Database\Eloquent\Model;

class CastImage extends Model
{
    protected $table = 'cast_images';

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
}
