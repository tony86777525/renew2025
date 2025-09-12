<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class AreaMain extends Model
{
    protected $table = 'area_mains';

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
