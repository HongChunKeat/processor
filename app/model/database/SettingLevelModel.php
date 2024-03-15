<?php

namespace app\model\database;

use app\model\database\DbBase;

class SettingLevelModel extends DbBase
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "setting_level";

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = "id";

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * All fields inside the $guarded array are not mass-assignable
     *
     * @var string
     */
    protected $guarded = ["id", "deleted_at"];
}
