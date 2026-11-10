<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Contract;
use App\Models\User;

/**
 * Single source of truth for "may this user see this contract?".
 *
 * Masters see everything; everyone else only sees contracts of the company
 * linked to their account (users.client_id → exporter_id / importer_id).
 * Kept in one place so the portal, the document endpoints and the tracking
 * endpoint can never drift apart.
 */
trait AuthorizesContractAccess
{
    private function authorizeContractAccess(Contract $contract): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        abort_unless($user->canAccessContract($contract), 403, __('You do not have permission to access this contract.'));
    }

    private function contractUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
