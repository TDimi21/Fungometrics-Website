<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

final class CreateServiceData
{
    public function __construct(private Model $model)
    {
    }

    /**
     * @param  array  $data
     * @return Model
     *
     * @throws NotCreated
     */
    public function handle(array $data): Model
    {
        try {
            return $this->model->create($data);
        } catch (QueryException $exception) {
            throw new NotCreated($exception->getMessage());
        }
    }
}
