<?php

namespace App\Http\Filters\Abstract;

use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Builder
     */
    protected $builder;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param Builder $builder
     */
    public function apply(Builder $builder): void
    {
        $this->builder = $builder;

        if ($orderBy = $this->request->get('order_by')) {
            $userField = UserField::select('id')
                ->where('code', $orderBy)
                ->first();

            $direction = $this->request->get('order_direction', 'asc');

            $model = $this->builder->getModel();

            // Если нашли пользовательское поле, то сортируем по нему,
            // а иначе сортируем по полю из модели на которую смотрит builder.
            if ($userField && method_exists($model, 'userFieldValues')) {
                $table = $model->getTable();

                $this->builder
                    ->select("$table.*")
                    ->with('userFieldValues')
                    ->leftJoin('user_field_values', function (JoinClause $join) use ($userField, $table) {
                        $join->on('user_field_values.entity_id', '=', "$table.id")
                            ->where('user_field_values.field_id', '=', $userField->id);
                    })
                    ->orderBy('user_field_values.value', $direction);
            } else {
                $fields = [
                    'who_changed', 'responsible', 'creator', 'sales_stage',
                    'sales_funnel', 'deal', 'role', 'division',
                ];

                if (in_array($orderBy, $fields)) {
                    $orderBy = $orderBy . '_id';
                }

                // Проверим, что поле по которому сортируем есть в модели.
                if (in_array($orderBy, $model->getFillable())) {
                    $this->builder->orderBy($orderBy, $direction);
                }
            }
        } else {
            foreach ($this->fields() as $method => $value) {
                if (method_exists($this, $method) && $value !== null) {
                    call_user_func_array([$this, $method], (array) $value);
                } else {
                    $this->userFieldFilter($method, $value);
                }
            }

            // По умолчанию сортируем от нового к старому.
            $this->builder->orderBy('id', 'desc');
        }
    }

    /**
     * @return array
     */
    protected function fields(): array
    {
        $requestData = $this->request->all();
        array_walk_recursive($requestData, function (&$value) {
            $value = is_string($value) ? trim($value) : $value;
        });

        return $requestData;
    }

    /**
     * @return void
     */
    protected function userFieldFilter(string $code, $value): void
    {
        if ($value !== null) {
            if (Str::endsWith($code, '_from')) {
                $code = str_replace("_from", "", $code);
                $userField = UserField::where("code", $code)->first();

                if ($userField) {
                    $value = $userField->type == "integer" ? (int)$value : $value;
                    $this->builder->whereIn("id",
                        UserFieldValue::where("field_id", $userField->id)->where("value", ">=",
                            $value)->pluck("entity_id"));
                }
            } else {
                if (Str::endsWith($code, "_to")) {
                    $code = str_replace("_to", "", $code);
                    $userField = UserField::where("code", $code)->first();

                    if ($userField) {
                        $value = $userField->type == "integer" ? (int)$value : $value;
                        $this->builder->whereIn("id",
                            UserFieldValue::where("field_id", $userField->id)->where("value", "<=",
                                $value)->pluck("entity_id"));
                    }
                } else {
                    $userField = UserField::where("code", $code)->first();
                    if ($userField) {
                        $this->builder->whereIn("id", UserFieldValue::where("field_id", $userField->id)->where("value",
                            $value)->pluck("entity_id"));
                    }
                }
            }
        }
    }
}
