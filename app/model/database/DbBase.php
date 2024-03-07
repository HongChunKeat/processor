<?php

namespace app\model\database;

use support\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DbBase extends Model
{
    use SoftDeletes;

    /**
     * @var string
     */
    protected $connection = "mysql";

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    public static function paging(
        array $filter = [],
        int $page = 1,
        int $limit = 5,
        array $columns = ["*"],
        array $sorted = [],
        array $join = []
    ) {
        $_response = false;

        $issetFilter = [];

        $table = self::select(...$columns);

        //left join
        if ($join) {
            foreach ($join as $data) {
                $table = $table->leftJoin(...$data);
            }

            // if got left join and want filter by id need make id become maintable.id then throw the default id
            if(isset($filter['id'])) {
                $filter[$sorted[0]] = $filter['id'];
                unset($filter['id']);
            }
        }

        //if got value then push to isset filter
        foreach ($filter as $key => $value) {
            if ((!empty($value) || $value == "0") && $value !== null) {
                $issetFilter[$key] = $value;
            }
        }

        if (count($issetFilter) > 0) {
            $table->where($issetFilter);
        }

        if ($sorted) {
            $table->orderBy(...$sorted);
        }

        $paginator = $table->paginate($limit, $columns, "page", $page);
        $_response = [
            "items" => $paginator->items(),
            "count" => $paginator->total(),
        ];

        return $_response;
    }

    public static function listing(
        array $filter = [],
        array $columns = ["*"],
        array $sorted = [],
        array $join = []
    ) {
        $_response = false;

        $issetFilter = [];

        $table = self::select(...$columns);

        //left join
        if ($join) {
            foreach ($join as $data) {
                $table = $table->leftJoin(...$data);
            }

            // if got left join and want filter by id need make id become maintable.id then throw the default id
            if(isset($filter['id'])) {
                $filter[$sorted[0]] = $filter['id'];
                unset($filter['id']);
            }
        }

        //if got value then push to isset filter
        foreach ($filter as $key => $value) {
            if ((!empty($value) || $value == "0") && $value !== null) {
                $issetFilter[$key] = $value;
            }
        }

        if (count($issetFilter) > 0) {
            $table->where($issetFilter);
        }

        if ($sorted) {
            $table->orderBy(...$sorted);
        }

        $listing = $table->get();
        $_response = $listing ?? [];

        return $_response;
    }
}
