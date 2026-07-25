<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

use App\Models\ConceptSubmission;
use App\Models\User;

final class ConceptSubmissionService
{
    public function submit(User $user, array $data): ConceptSubmission
    {
        return ConceptSubmission::query()->create($data + ['submitted_by' => $user->id,'status' => 'pending']);
    }
}
