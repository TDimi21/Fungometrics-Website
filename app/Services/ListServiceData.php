<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFound;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListServiceData
{
    public function __construct(private Model $model)
    {
    }

    /**
     * @return Collection
     *
     * @throws NotFound
     */
    public function all(): Collection
    {
        $result = $this->model->all();
        if (0 === $result->count()) {
            throw new NotFound();
        }

        return $result;
    }

    /**
     * @param string $uuid
     * @return Model
     *
     * @throws NotFound
     */
    public function findByUuid(string $uuid): Model
    {
        $result = $this->model->find($uuid);
        if (null === $result) {
            throw new NotFound();
        }

        return $result;
    }

    /**
     * @param string $colum
     * @param string $value
     * @param string $operator
     * @return Model|null
     */
    public function byParamFirst(string $colum, string $value, string $operator = '='): Model|null
    {
        return $this->model->where($colum, $operator, $value)->first();
    }

    /**
     * @param string $colum
     * @param string $value
     * @param string $operator
     * @return Model|null
     */
    public function byParamAll(string $colum, string $value, string $operator = '='): Model|null
    {
        return $this->model->where($colum, $operator, $value)->get();
    }

    public function byParamAllPaginate(string $colum, string $value, string $operator = '='): LengthAwarePaginator
    {
        return $this->model->where($colum, $operator, $value)->paginate();
    }

    /**
     * @param array $conditions
     * @return Collection
     *
     * @throws NotFound
     */
    public function byParams(array $conditions): Collection
    {
        return $this->model->where($conditions)->get();
    }
}
