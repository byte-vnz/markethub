# Task5: Final independent review and verification

- [ ] Obtain whole-change fresh security/financial review, adjudicate/fix confirmed findings with red/green checks and ledger evidence. Compare source against checkpoint and verify original XLSX/DOCX hashes and secret exclusion.
- [ ] Run full forced-SQLite suite, native lint, php scripts/check-php74.php, php artisan view:cache, npm run build, node syntax and documentation self/source checks. Report actual results, not predictions.
- [ ] Stop verified temporary preview process and remove explicit temporary DB/key/login/scripts; retain non-sensitive evidence. Update plan/ledger/status with source-complete vs deployment-pending distinction. Do not merge/deploy/run destructive migrations.

## Binding global constraints


- No dependency upgrade, replacement module, duplicate section FK or production data write.
- Preserve section IDs/names/scoped codes, market_section_id and all map coordinates.
- Automatic remains default: Inner minimum, Outer maximum. Use frozen initial category/area/CUSA/day basis for established assignments.
- Override activation/revocation is prospective on full-month boundaries; no proration or automatic posting.
- Separate requester/approver; policy must cover the entire override period. No implicit out-of-range/zero authority.
- Existing assessments, bills, contract snapshots/files, payments and balances remain unchanged. No destructive database command.
- Tests force isolated SQLite; native PHP is8.2.12, actual7.4/MySQL contention/Word fidelity remain unverified unless explicitly checked.
- No Git repository: work in the approved workspace using a protected source ZIP/hash checkpoint, not invented commits/worktrees.
- User has explicitly approved implementation; no second plan approval gate or between-task permission request.

