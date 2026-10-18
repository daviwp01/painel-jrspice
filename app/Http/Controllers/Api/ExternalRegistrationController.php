<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExternalRegisterRequest;
use App\Services\ExternalRegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * Anonymous endpoint behind the public registration form.
 *
 * The perimeter lives in the FormRequest (origin allowlist, honeypot and form
 * token) and in the rate limiter attached to the route; this controller only
 * hands the request to the service and returns the same answer for every
 * outcome, so it cannot be used to probe accounts.
 */
class ExternalRegistrationController extends Controller
{
    public function __construct(private readonly ExternalRegistrationService $registrations)
    {
    }

    public function store(ExternalRegisterRequest $request): JsonResponse
    {
        $answer = $this->registrations->register($request);

        return response()->json([
            'success' => true,
            'message' => $answer['message'],
        ], $answer['status']);
    }
}
