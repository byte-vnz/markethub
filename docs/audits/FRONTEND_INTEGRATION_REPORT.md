# Modern Web Guidance integration report

Completed 20 November 2026, Asia/Manila. **Integration, source audit, standards and roadmap complete. Frontend modernization implementation awaits explicit approval.** Existing approved Section/rental override functionality remains the product baseline.

## Installation and coordination

Official Modern Web Guidance is installed in `.agents/skills/modern-web-guidance/`: **211 Markdown files** (SKILL.md plus supplied guides), source commit `64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65`, skill marker `2026_11_10-47b4c80e`. CLI **1.0.1** is pinned by the project runner. The inspected official CLI installer delegated to **skills 1.9.0** with Codex/project-only/copy flags. `skills-lock.json` records the revision/content hash. Codex added the new project entrypoint to its available-skills catalog during the session, confirming discovery; generic CLI invocation also succeeded.

Existing Modern Web Guidance host/plugin copies, Superpowers 7.0.1, Ponytail 5.3.3, local UI/UX Pro Max and Graphify were retained. No extra skill pack, global installation, app dependency or framework upgrade was made. Use the project copy once; workflow ownership, exact paths, dependencies, unversioned-copy limitations and update procedure are in [SKILLS_REGISTRY.md](../codex/SKILLS_REGISTRY.md). Graphify had no existing project graph; direct source tracing supplied the impact analysis without graph extraction or external model calls.

Source verification used the [official distribution](https://github.com/GoogleChrome/modern-web-guidance), its installer/source code, README and license information. Downloaded CLI archive matched npm's published SHA-512 integrity. Software is Apache-2.0, guide content CC-BY-4.0; preserve upstream attribution. The registry records the exact integrity string and installation command.

## Files created and modified

| Change | Paths |
| --- | --- |
| Project skill installation | `.agents/skills/modern-web-guidance/SKILL.md` and 210 supplied guide Markdown files |
| Tooling/configuration | `skills-lock.json`, `.mwgrc`, `scripts/modern-web-guidance.ps1` |
| Development documentation | `docs/codex/SKILLS_REGISTRY.md`, `docs/audits/FRONTEND_MODERNIZATION_AUDIT.md`, `docs/plans/FRONTEND_MODERNIZATION_ROADMAP.md`, this report |
| Existing instructions appended | `AGENTS.md`, `docs/codex/PROJECT_MEMORY.md`, `CURRENT_STATE.md`, `DECISIONS.md`, `HANDOFF.md` |
| Local evidence (already ignored) | `.qa/modern-web-guidance/`: reviewed public package/installer material, source hash inventory, instruction snapshot, install/scan/search/privacy/runner/test/build logs and bounded review evidence; npm download cache stays here |

No application controller/view/JS/CSS/service, routes, permissions, schema, Composer/npm application manifests or locks were changed. Rebuilding the local assets produced identical bytes. Existing operator manual v1.2 text/screenshots/diagrams/PDF/DOCX were preserved: no product UI/workflow/navigation/permission change occurred, so regeneration would not document a new product state. Future approved UI changes must follow the existing manual maintenance rules.

## Privacy and compatibility settings

`scripts/modern-web-guidance.ps1` sets `DISABLE_TELEMETRY=1`, `DO_NOT_TRACK=1`, `ENABLE_FILE_LOGGING=false`, `NPM_CONFIG_IGNORE_SCRIPTS=true` and a project-local npm cache for its process/children, then restores the caller environment. Root AGENTS requires that runner over the upstream bare examples. `.mwgrc` sets Baseline `widely`. No production `.env`, shell profile, Apache or PHP/MySQL configuration was changed.

Generic technical queries only were used. No live database connection was opened, and no tenant/payment/credential/private-document contents were uploaded to external services. The inspected CLI uses local search indexes and packaged guides; it requires no external model API. Installation/cache misses/updates still contact public package/repository services, which see connection metadata. Opt-outs are not a general network sandbox and do not cover arbitrary future versions or unrelated npm behavior.

Runtime privacy validation blocked fetch/HTTP/HTTPS/socket APIs and child processes inside the inspected CLI. Search, retrieve and legacy-scan still completed successfully with the documented opt-out. An enabled-telemetry negative control was intercepted before the telemetry child launched. This confirms those tested code paths; it is not packet capture. The first sandboxed harness attempt hit Node child-process EPERM; the authorized local instrumented rerun passed. No telemetry was sent by the control.

Installed versions were inspected: PHP CLI **8.2.12**, Laravel **8.83.29**, Spatie Permission **5.11.1**, PHPUnit **9.6.39**, Tailwind **3.4.17**, Chart.js **4.4.9**, SweetAlert2 **11.22.4**, Node **24.12.0**, npm **11.19.0**. MySQL server executable reports **8.4.7**; this does not assert a new authenticated live-server check. Target remains PHP7.4.30-compatible Laravel8.83 on Windows/XAMPP; no stack replacement.

## Audit and proposed phases

Full source coverage spans all 63 Blade templates, three client scripts, shared CSS/layout, navigation, assets and relevant request/rendering paths across every current module. Two bounded read-only reviewers validated forms/accessibility and layout/performance; the coordinator checked evidence and consolidated findings. Their final consistency review found no actionable mismatch within those scopes. This is not independent runtime accessibility certification.

| Priority | Count | Summary |
| --- | ---: | --- |
| P0 — Critical | 0 | None established in this bounded audit |
| P1 — High | 0 | None established in this bounded audit |
| P2 — Medium | 7 | Error associations/focus; staff failure recovery; misleading Terminate confirmation; table keyboard regions; no-JS mobile navigation; drawer semantics; Help image loading/dimensions |
| P3 — Low | 4 | Required-state parity; distinct action names; page-specific Chart loading; legacy-rate relation queries |

Official legacy scanner: **0 matches**, **52 rules / 38 guides**, approximately 20% corpus coverage. A zero scan was not treated as a clean accessibility/security verdict. Supporting files, line references, guide IDs, recommended fixes, risks and acceptance criteria are in the [audit](FRONTEND_MODERNIZATION_AUDIT.md).

The [roadmap](../plans/FRONTEND_MODERNIZATION_ROADMAP.md) proposes: (1) foundations/concrete defects, (2) layout/design consistency, (3) core business workflow usability, (4) measured performance, (5) cross-browser/accessibility/print/regression/documentation QA. Each stage specifies dependencies, risks and measurable acceptance. Recommended next action is approval of bounded Stage1; no remediation has been implemented.

## Executed verification

| Check / command | Actual result and evidence |
| --- | --- |
| `C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --colors=never --do-not-cache-result` | **PASS: 237 tests, 1,903 assertions**, 09:48.289, 76MB. `phpunit.xml` forces testing and SQLite `:memory:`; MySQL suite excluded. `.qa/modern-web-guidance/phpunit.txt`. Includes existing auth, authorization, financial, immutable-contract, Section/override and authenticated Help tests. |
| `C:\xampp\php\php.exe scripts/check-php74.php` | **PASS: 309 PHP files, 0 grammar failures**, including compiled views present at execution. Actual PHP7.4 execution remains unperformed. `.qa/modern-web-guidance/php74.txt`. |
| `node --check` for `resources/js/app.js`, `tenant-registration.js`, `rental-overrides.js` | **PASS**, exit0 for the three existing scripts. No code changed. |
| `npm.cmd run build` | **PASS**, Tailwind plus local asset copying. Existing caniuse-lite/Browserslist stale-data warning remains; no upgrade run. `.qa/modern-web-guidance/build.txt`. |
| Project runner `--version` and `search 'responsive data table'` | **PASS**, reports1.0.1 and returns matching guide IDs. `.qa/modern-web-guidance/runner-search.json`. |
| Official generic searches + retrieve forms/accessibility/responsive-table/navigation-drawer/performance/html | **PASS**, guides read in full by bounded reviewers. No product-code application claimed. |
| Official `legacy-scan resources --json` | **PASS**,0matches/52rules/38guides. `.qa/modern-web-guidance/legacy-scan.json`. |
| `node .qa/modern-web-guidance/privacy-check.cjs` | **PASS** in authorized local run; all three commands run with network/child guards, enabled-telemetry control blocked. `privacy-results.json`. |
| `.qa/modern-web-guidance/runner-check.ps1` | **PASS**, package pin/argument forwarding, opt-outs, local cache, caller restoration and failure exit-code propagation. `runner-check.txt`. |
| Source preservation | SHA256 baseline of541source/asset/document files and original AGENTS prefix comparison; only the5intended pre-existing instruction files changed, no missing baseline files. `before-hashes.json`, `preservation.json`, `instructions-before.zip`. |

No `migrate:fresh`, live migration/backfill/seed, dependency upgrade, deployment or real-data browser walkthrough was run. SQLite schema setup occurred only inside the existing disposable tests. No user account was provisioned. Existing application tests render routes/views; a separate live Blade-cache command was unnecessary for this tooling/documentation-only change.

## Git summary and rollback

`git status --short` reported that this folder is not a Git repository. No branch, staging, commit, PR or invented diff is claimed. The instruction ZIP and SHA256 manifests provide a reviewable comparison while preserving existing protected project checkpoints. New deliverables are listed above; only AGENTS and the four requested codex documents have changed among pre-existing files.

To revert this integration, restore only those five instruction files from the snapshot and remove only the new project skill, lock/config/runner and integration docs after checking for subsequent edits. Keep existing application files, global skills, user data and older recovery checkpoints. There is no database rollback for this change.

## Remaining limits and approval gate

No new browser/device/screen-reader/contrast/CWV/network/print/Word test was performed. Source findings require runtime acceptance during an approved modernization stage. All existing MySQL concurrency/operator rollout, actual PHP7.4, Word/legal review and LGU policy/UAT limits remain. Previously recorded unsupported-stack/advisory restrictions are unchanged; this integration is not internet-facing production approval. Current dependency advisories were not refreshed.

The task stops here at the user's requested modernization approval gate. Review the concrete Stage1 scope and approve it before any UI implementation. Existing deployment and official-policy permissions are separate.
