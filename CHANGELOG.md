# Changelog

## 2.1.0

### Added

- `finance.integrity.acknowledged`: legacy transactions whose signature failure was investigated, as id => reason.
  `finance:verify-integrity` reports them as warnings and counts them in `acknowledged_transactions`; the chain,
  wallets and balances are still verified.
- `finance.integrity.acknowledged_mode` (`FINANCE_INTEGRITY_ACKNOWLEDGED_MODE`): `rounding` (default) accepts an entry
  only when its signatures verify with the `start_log` amount rounded to the stored one (amounts signed with
  6 decimals before 2.0); `any` accepts every listed id.
- Warnings for listed ids that no longer exist or whose signature verifies again.

### Fixed

- `finance:expire-pending` no longer stops at the first transaction it cannot cancel (e.g. a
  `CompromisedTransactionException` on a start signature that fails): each failure is logged (id and reason only),
  counted, and the command goes on. Output: `X pending transaction(s) canceled, Y skipped (A acknowledged, F failed)`.
  Exit code 1 only when a non-acknowledged transaction failed.
- Acknowledged PENDING transactions are skipped by `finance:expire-pending` and stay PENDING, untouched. Canceling them
  would compute a checksum over a start signature that fails, which amounts to re-signing them and would erase the
  proof; skipping keeps the original signature, and a PENDING row counts in no balance. A listed id that the
  `rounding` mode does not accept is still attempted and reported as a failure.

## 2.0.0

- Signature v3 (HMAC-SHA256 with `FINANCE_SIGNATURE_KEY`, key id and rotation); v1 and v2 rows are still verified.
- Hash chain, signed audit log and the `finance:verify-integrity`, `finance:seal-history` and `finance:expire-pending`
  commands.
- Atomic, idempotent finalization; pending debits reserved while checking a new debit.
- Translatable `display` (`TransactionDisplay`, signed) and indexed tags (`tagged()`, `taggedAny()`).
- No runtime dependency on nycorp/lite-api; same JSON responses.
