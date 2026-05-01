<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeleteException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class DeleteServiceData
{
    public function __construct(private Model $model)
    {
    }

    public function handle(string $id): bool
    {
        try {
            return $this->model->findOrFail($id)->delete();
        } catch (Exception $exception) {
            $message = 'NOT DELETE DATA';
            if ($exception instanceof ModelNotFoundException) {
                $message = 'NOT DELETE, Not Data Found ';
            }

            throw new DeleteException();
        }
    }
}
