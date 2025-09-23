<?php

namespace App\Models\Chocolat;

use Illuminate\Database\Eloquent\Model;

class TissueNightRecommendPc extends Model
{
    protected $table = 'tissue_night_recommend_pc';

    /**
     * 開放程式碼新增或編輯的欄位
     * 避免誤改其他欄位
     */
    protected $fillable = [
        'tissue_type',
        'tissue_id',
        'pref_id',
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
