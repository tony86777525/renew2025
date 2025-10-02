<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class TissueComment extends Model
{
    protected $table = 'tissue_comment';

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

    public function scopeNotDeleted($query)
    {
        return $query->where('tissue_comment.del', 0);
    }

    public function scopePageCtrl($query, $perPage = 10, $currentPage = 1, $skipAdd = 0){

        $total = $query->count();

        $page = $currentPage;
        $skip = ($page - 1 ) * $perPage + $skipAdd;

        $results = $total
            ? $query->skip($skip > 0?$skip:(($page - 1) * $perPage))->take($perPage)->get()
            : collect();

        return ['total' => $total, 'items' => $results];
    }
}
