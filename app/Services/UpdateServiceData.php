<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UpdateException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class UpdateServiceData
{
    public function __construct(private Model $model)
    {
    }

    /**
     * @param  string  $id
     * @param  array  $data
     * @return Model
     *
     * @throws UpdateException
     */
    public function handle(string $id, array $data): Model
    {
        try {
            $tempData = $this->model->findOrFail($id);

            return tap($tempData, fn ($model) => $model->update($data));
        } catch (Exception $exception) {
            if ($exception instanceof ModelNotFoundException) {
                throw new UpdateException('NOT UPDATE, Not Data Found');
            }
            throw new UpdateException('NOT UPDATE DATA');
        }
    }
}
