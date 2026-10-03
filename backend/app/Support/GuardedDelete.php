<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Delete reference data that other records may point at.
 *
 * "In use" is enforced by the database: every referencing table declares its
 * foreign key ON DELETE RESTRICT, and the resulting integrity error becomes a
 * 409 with a readable message. New modules get this protection just by
 * declaring their FK correctly — there is no list of dependents to maintain.
 */
final class GuardedDelete
{
    public static function delete(Model $model, string $inUseMessage): void
    {
        try {
            $model->delete();
        } catch (QueryException $e) {
            // SQLSTATE 23000: integrity constraint violation (FK restrict).
            if ($e->getCode() === '23000') {
                abort(409, $inUseMessage);
            }

            throw $e;
        }
    }
}
