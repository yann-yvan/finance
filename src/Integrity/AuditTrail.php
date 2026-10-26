<?php

namespace NYCorp\Finance\Integrity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NYCorp\Finance\Models\FinanceAuditLog;
use Throwable;

/**
 * Writes signed, append-only audit rows: who changed which state or recalculated which balance, and from where.
 * Values must not contain personal data.
 */
class AuditTrail
{
    public static function record(string $event, Model|array|null $subject = null, ?array $old = null, ?array $new = null): ?FinanceAuditLog
    {
        if (!FinanceSchema::hasAuditLog()) {
            return null;
        }

        [$subjectType, $subjectId] = match (true) {
            $subject instanceof Model => [$subject->getMorphClass(), $subject->getKey()],
            is_array($subject) => [$subject[0] ?? null, $subject[1] ?? null],
            default => [null, null],
        };
        [$actorType, $actorId] = self::actor();

        $log = new FinanceAuditLog([
            FinanceAuditLog::EVENT => $event,
            FinanceAuditLog::SUBJECT_TYPE => $subjectType,
            FinanceAuditLog::SUBJECT_ID => $subjectId === null ? null : (string)$subjectId,
            FinanceAuditLog::OLD_VALUES => $old,
            FinanceAuditLog::NEW_VALUES => $new,
            FinanceAuditLog::ACTOR_TYPE => $actorType,
            FinanceAuditLog::ACTOR_ID => $actorId,
            FinanceAuditLog::CONTEXT => self::context(),
            FinanceAuditLog::CREATED_AT => now(),
        ]);
        $log->{FinanceAuditLog::HASH} = Signer::sign($log->payload());
        $log->save();

        return $log;
    }

    private static function actor(): array
    {
        try {
            $user = auth()->user();
            if ($user instanceof Model) {
                return [$user->getMorphClass(), (string)$user->getKey()];
            }
        } catch (Throwable) {
        }
        return [null, null];
    }

    private static function context(): string
    {
        if (app()->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];
            return Str::limit('console:' . implode(' ', array_slice($argv, 1, 1)), 250, '');
        }

        try {
            $request = request();
            $route = $request->route()?->getName() ?? $request->path();
            return Str::limit('http:' . $request->method() . ' ' . $route, 250, '');
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
