<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class WeeklyRankingPoint extends Model
{
    protected $table = 'weekly_ranking_points';

    /**
     * 開放程式碼新增或編輯的欄位
     * 避免誤改其他欄位
     */
    protected $fillable = [
        'tissue_from_type',
        'post_user_id',
        'choco_cast_id',
        'night_cast_id',
        'point',
        'tissue_count',
        'is_aggregated',
        'ranking_cumulative_start_date',
        'created_at',
    ];

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
