<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\AccountClaimService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ResolveAccountClaim
{
    public function __construct(private AccountClaimService $claims)
    {
    }

    public function handle(Request $request, Closure $next, ?string $intendedType = null): Response
    {
        try {
            $claim = $this->claims->resolve((string) $request->route('claim'), $intendedType);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'This account claim is invalid, expired, or already used.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $request->attributes->set('account_claim', $claim);
        $request->route()->setParameter('user', $claim->user);

        return $next($request);
    }
}
