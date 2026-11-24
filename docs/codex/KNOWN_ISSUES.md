# Known issues and acceptance limits

- MySQL setup/migrations deferred by the user. Server version, SQL integration, row-lock/deadlock behavior and final persistence acceptance have not been verified. SQLite tests do not establish MySQL concurrency correctness.
- Installed PHP is 8.2.12. Composer platform and syntax parser target 7.4.30; actual PHP 7.4.30 runtime validation remains required.
- Unsupported Laravel 8/PHP 7.4 stack has seven recorded dependency advisories (six Laravel framework, one Flysystem), plus abandoned packages. See operations/composer-audit.json and DEPLOYMENT.md. No internet-facing production approval.
- Apache Alias is prepared and syntax-checked, not activated. Root .htaccess denies access until public-only mapping is installed. No shared Apache/PHP configuration was changed.
- Full-month billing only; partial-month assignments cannot be billed by guessing proration. Advances/overpayments, automatic penalties and automatic posting are disabled pending LGU rules. Existing explicit backdate permission and Treasury confirmation do not establish legal LGU authority.
- Posted bill correction uses append-only signed adjustments. Dedicated bill-void workflow, configurable approved charge item entry and chronological running-balance account statement remain future work.
- Staff permissions apply across this LGU installation; per-market staff scope, custom permission designer and tenant multi-stall cap are not implemented. Confirm policy before entering restricted real data.
- Receipt numbers are recorded references only; no official receipt issuance integration exists.
- Browser checks covered desktop/tablet map and payment flow, not a full assistive-technology audit, all mobile devices or final XAMPP deployment. Export content tests pass; every possible long real-data PDF layout has not been visually reviewed.
- Build emits a nonfatal stale Browserslist data warning. No application test failures remain at this checkpoint.
- The workspace was initially empty and is not a Git repository; no commits were created. Locked dependencies are present for reproducibility.
