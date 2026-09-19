# MT-7.2 Dependency / License / Notice Audit

## Scope

Audit date: 2026-09-19. Inputs are the current Composer/npm lockfiles, installed package metadata and canonical brand/runtime assets. This is a technical release audit, not legal approval.

## Findings

- Root application LICENSE: absent by design pending explicit owner license decision.
- `backend/composer.json` incorrectly inherited `license: MIT` from the Laravel starter. MT-7.2 removes that field so framework metadata cannot be mistaken for a mobiST application license.
- Backend npm production graph: 10 packages, all MIT.
- Website npm production graph: 54 packages. License counts from the current lockfile: MIT 19; Apache-2.0 16; LGPL-3.0-or-later 10; Apache-2.0 AND LGPL-3.0-or-later AND MIT 1; Apache-2.0 AND LGPL-3.0-or-later 3; CC-BY-4.0 1; ISC 2; BSD-3-Clause 1; 0BSD 1.
- Composer graph includes MIT, BSD-3-Clause and Apache-2.0 dependencies plus Nette packages offering BSD/GPL alternatives. Exact versions/licenses are pinned in `backend/composer.lock` and available via `composer licenses`.
- Sharp/libvips and caniuse-lite are the material Website attribution/distribution items requiring release attention. `NOTICE.md` records the posture.
- Canonical brand artwork is project-approved first-party material; no font binary is bundled by the project.

## Release posture

`NOTICE.md` is now the root notice artifact. Exact dependency license texts remain in package distributions/vendor metadata and must not be stripped. MT-7.5/FINAL-AUDIT must compare actual shipped contents against lockfiles because optional platform packages may differ by release target.

Project-level proprietary/open-source terms remain an unresolved owner decision. The audit deliberately does not create a root LICENSE or claim legal approval.