# Development skills registry

Integration completed in the 19–20 November 2026 session. This registry covers the skills relevant to MarketHub; the host also offers unrelated artifact/product skills. No new application package or global skill was installed. Project policy and the user's task scope outrank generic guidance.

## Modern Web Guidance

| Item | Verified value |
| --- | --- |
| Official distribution | https://github.com/GoogleChrome/modern-web-guidance |
| Source implementation | https://github.com/GoogleChrome/modern-web-guidance-src |
| Documentation | https://developer.chrome.com/docs/modern-web-guidance |
| Project location | `.agents/skills/modern-web-guidance/SKILL.md` and its supplied `guides/` |
| Installed source commit | `64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65` |
| Skill marker | `2026_11_10-47b4c80e` in the upstream instructions |
| Runtime CLI | `modern-web-guidance@1.0.1`, pinned by `scripts/modern-web-guidance.ps1` |
| Installer | Official CLI `install`, delegating to inspected `skills@1.9.0`; only `--agent codex --yes --copy`, no global flag |
| Lock | Root `skills-lock.json`, version 2; computed skill hash `d9f3ceba8b5c77b12ae6f852932a349843ce086d03ce76ab5e2257e7e5acd16d` |
| Requirements | CLI Node >=22; installer Node >=22.20.0. Installed Node 24.12.0/npm 11.19.0 satisfy both. No PHP dependency. |
| Discovery | Official installer identifies `.agents/skills` for Codex; the host added this exact project SKILL.md to its available-skills catalog during the session. Instructions were read and generic search/retrieve/scan commands executed. |
| Activation | Applicable HTML/CSS/Blade markup/client JS, accessibility, responsive behavior, performance and browser compatibility. Skip backend-only/tooling changes. |
| Scope limits | Guidance and deterministic legacy scans are advisory. They neither test the running application nor establish WCAG compliance. The scanner checks 38 guides/52 rules, about 20% of the corpus in this version. |

### Installation provenance and duplicates

The official package was downloaded from npm without executing it, and its bytes were checked against npm's published SHA-512 integrity:

`sha512-5ztUh5F1p2PewnSkA6EhsGUXqomaZY9qe6iWJCU4JbIQxHTky7s5lMJcPE4EbD1uhbQZIhl0HROH0+s6fIiGzg==`

The inspected package has no runtime dependencies or lifecycle scripts. Its install command delegates to Vercel's skills installer; that installer and its documentation were inspected before execution (MIT; runtime dependencies tar, yaml and nostics). npm lifecycle scripts were disabled. The command executed from the project root was:

```powershell
$env:DISABLE_TELEMETRY='1'
$env:DO_NOT_TRACK='1'
$env:NPM_CONFIG_IGNORE_SCRIPTS='true'
$env:NPM_CONFIG_CACHE=Join-Path $PWD '.qa/modern-web-guidance/npm-cache'
node .qa/modern-web-guidance/package/skills/modern-web-guidance/modern-web.mjs install --agent codex --yes --copy
```

This is the official `modern-web-guidance install` entry point from the integrity-checked 1.0.1 package; running it directly avoided executing an uninspected floating package. It fetched the official repository and recorded the exact commit above. Source code and documentation licenses differ: Apache-2.0 for software, CC-BY-4.0 for guide content; retain upstream notices/attribution when redistributing. See the [official license notice](https://github.com/GoogleChrome/modern-web-guidance#license), [Apache license](https://github.com/GoogleChrome/modern-web-guidance/blob/64a6d708f5a43f2cd3fde5d4d7db3905ba1bdf65/LICENSE), and upstream attribution in supplied guides.

Existing host copies remain at `C:/Users/vnz/.agents/skills/modern-web-guidance` and the GoogleChrome plugin cache under `C:/Users/vnz/.codex/plugins/cache/googlechrome/modern-web-guidance/1.0.1`. One project copy was added because portable project integration was explicitly requested. No second project copy or Chrome Extensions pack was installed. Do not remove shared global skills to resolve this intentional overlap: activate this project's copy once and use its runner.

### Privacy configuration and limits

Use the runner from the project root:

```powershell
./scripts/modern-web-guidance.ps1 search 'accessible form validation'
./scripts/modern-web-guidance.ps1 retrieve 'forms,accessibility'
./scripts/modern-web-guidance.ps1 legacy-scan resources --json
```

The runner sets `DISABLE_TELEMETRY=1`, `DO_NOT_TRACK=1`, `ENABLE_FILE_LOGGING=false`, `NPM_CONFIG_IGNORE_SCRIPTS=true` and a project-local npm cache for its process/children, restoring the caller's environment afterward. No `.env`, shell profile, global configuration or production configuration is changed. Upstream SKILL.md is unmodified; the root AGENTS instruction to use this runner takes precedence over its generic `npx ...@latest` examples. `.mwgrc` sets `baseline: widely`; it is not a telemetry control.

Inspected CLI code normally sends search strings/results, retrieved guide IDs, CLI/skill version, agent, OS, success and latency buckets through a detached telemetry worker. The documented opt-out returns before that worker is created. The installer separately honors both opt-out variables and disables its telemetry and remote security-audit requests. Disabling those audits is not a security certification of the installed skill; source inspection remains necessary.

Search uses bundled local indexes/embeddings, retrieval reads packaged guides, and legacy scanning reads local source. No AI API key or hosted inference service is required. A runtime self-check passed all three commands with network APIs and child-process creation blocked inside the CLI; a telemetry-enabled negative control was intercepted before launch. Evidence: `.qa/modern-web-guidance/privacy-results.json` and its two self-check files. This is an instrumented code-path check, not packet capture or a guarantee about future versions.

Initial install, cache misses and updates require public GitHub/npm connections; those services receive ordinary connection metadata. npm itself is outside the CLI instrumentation. A cached CLI can be invoked offline using the same opt-out environment and `npx.cmd --offline --yes modern-web-guidance@1.0.1 ...`. Never use records, names, amounts, credentials, source documents, database extracts or confidential identifiers as search terms. Scan only approved source directories, not `.env`, `storage`, private templates, exports, databases, or the entire XAMPP tree. Do not enable origin trials, volatile features, analytics or external AI features through guidance defaults.

### Update procedure

1. Read the official release notes, README, licenses, install instructions, package metadata and privacy code for the proposed version; inspect any new dependencies/scripts. Use generic technical queries only.
2. Snapshot `.agents/skills/modern-web-guidance`, `skills-lock.json`, this registry and the runner. Preserve all other skills.
3. With both telemetry opt-outs and npm lifecycle scripts disabled, use the inspected official installer for only `modern-web-guidance` and Codex at project scope. For a pinned reviewed source use the supported delegated command `npx.cmd --yes skills@1.9.0 add GoogleChrome/modern-web-guidance@<reviewed-commit> --skill modern-web-guidance --agent codex --yes --copy` (re-review installer if changing its version).
4. Record the new commit/hash, update the runner's CLI pin intentionally, re-read SKILL.md, verify catalog discovery, run a generic search/retrieve and the privacy self-check against the updated CLI. Do not enable auto-update or silently adopt `latest`.
5. Keep `.mwgrc` widely and rerun relevant audit checks. Rollback restores only these tooling files from the reviewed snapshot; no database rollback.

## Coordination of existing skills

| Skill / source | Observed version/location | Responsibility and activation | Dependencies/privacy/limits |
| --- | --- | --- | --- |
| Superpowers — https://github.com/obra/superpowers | Host cache `openai-curated-remote/superpowers/7.0.1/skills` | Requirements, brainstorming, scoped plans, debugging, TDD for product logic, bounded independent review and verification. User has already authorized this integration; modernization awaits approval. | Host tools; do not install dependencies or perform deployment merely because a generic workflow suggests it. Historical project plans remain historical. |
| UI/UX Pro Max — https://github.com/nextlevelbuilder/ui-ux-pro-max-skill | `C:/Users/vnz/.agents/skills/ui-ux-pro-max/SKILL.md`; local copy has no verified release marker | Visual hierarchy, forms, layout consistency, accessible interactions. Reuse existing navy/sidebar/panel design and existing components. | Local Python search data when needed. Do not treat a preferred 44px touch target as the exact WCAG AA threshold, or install UI frameworks from generic examples. Upstream project verified; installed-copy release provenance not verified in this task. |
| Graphify — https://github.com/Graphify-Labs/graphify (former safishamsi URL redirects here) | `C:/Users/vnz/.codex/skills/graphify/SKILL.md`; CLI found in user's Python314 Scripts directory; release not verified | Optional local graph/relationship exploration; no project graph exists. This audit uses direct route/controller/view/JS tracing instead. | Full graph extraction can call external model services when keys exist. No extraction/upload/install was run; no provider keys were read. No graph freshness claim. |
| Ponytail — https://github.com/DietrichGebert/ponytail | Host cache `ponytail/ponytail/5.3.3/skills/ponytail`; package metadata confirms version/source | Smallest complete change, avoid added runtime dependencies and unjustified refactors. Active full level. | Instruction-only workflow used here; no extra command or network service needed. |
| Security/privacy | Existing project security checklist, policies/Form Requests and Modern Web Guidance security/privacy disciplines | Review authorization, CSRF, escaping, private data, immutable accounting and safe development tooling. | No dedicated security plugin was installed or claimed present. This frontend audit is not a penetration test or refreshed dependency-vulnerability certification. |
| Skill installer | `C:/Users/vnz/.codex/skills/.system/skill-installer/SKILL.md`; bundled host version unmarked | Installation safety/discovery conventions consulted. Requested upstream official installation path used. | No extra global install; no unrelated skill added. |

Existing host skill entrypoints were left unchanged. Their presence/readability is verified; executing every unrelated skill is outside scope. Only the project Modern Web Guidance entrypoint and development runner were newly integrated. Never infer that every skill in the host catalog should run on every task.

For existing host skills, update through their owning plugin/skill manager only when explicitly requested; inspect that release's instructions and privacy behavior first. This integration does not enable automatic updates or replace shared installations. For unversioned local copies, establish source revision/hash before an update instead of inferring a version from the upstream current release.
