<?php

namespace App\Models\Chocolat;

use App\Facades\Taxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class YoasobiShopsAll extends Model
{
    protected $table = 'yoasobi_shops_all';

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
}
