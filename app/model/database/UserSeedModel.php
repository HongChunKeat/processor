<?php

namespace app\model\database;

use app\model\database\DbBase;
use zjkal\TimeHelper;

class UserSeedModel extends DbBase
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "user_seed";

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = "id";

    /**
     * All fields inside the $guarded array are not mass-assignable
     *
     * @var string
     */
    protected $guarded = ["id", "deleted_at"];

    /**
     * Get the formatted created_at timestamp.
     *
     * @var string
     */
    public function getCreatedAtAttribute($value)
    {
        return TimeHelper::format("Y-m-d H:i:s", $value);
    }

    /**
     * Get the formatted updated_at timestamp.
     *
     * @var string
     */
    public function getUpdatedAtAttribute($value)
    {
        return TimeHelper::format("Y-m-d H:i:s", $value);
    }
}
