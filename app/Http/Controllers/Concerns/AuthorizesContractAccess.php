<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Contract;
use App\Models\User;

/**
 * Single source of truth for "may this user see this contract?".
 *
 * Masters see everything; everyone else must be linked through the
 * contract_users pivot. Kept in one place so the portal, the document
 * endpoints and the tracking endpoint can never drift apart.
 */
trait AuthorizesContractAccess
{
    private function authorizeContractAccess(Contract $contract): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(401);
        }

        if ($user->is_master) {
            return;
        }

        $linked = $contract->users()
            ->where('users.id', $user->id)
            ->exists();

        abort_unless($linked, 403, 'Você não tem permissão para acessar este contrato.');
    }

    private function contractUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
