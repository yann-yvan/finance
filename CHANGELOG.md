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

## 2.0.0

- Signature v3 (HMAC-SHA256 with `FINANCE_SIGNATURE_KEY`, key id and rotation); v1 and v2 rows are still verified.
- Hash chain, signed audit log and the `finance:verify-integrity`, `finance:seal-history` and `finance:expire-pending`
  commands.
- Atomic, idempotent finalization; pending debits reserved while checking a new debit.
- Translatable `display` (`TransactionDisplay`, signed) and indexed tags (`tagged()`, `taggedAny()`).
- No runtime dependency on nycorp/lite-api; same JSON responses.
