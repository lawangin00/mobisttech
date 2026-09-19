# Third-Party Notices

This file records the deliberate third-party attribution/distribution posture for the private mobiST Tech repository. It is not a license grant for mobiST-owned application code.

## Application ownership/license status

- The repository currently has no root project LICENSE.
- The prior Laravel-starter `MIT` field in `backend/composer.json` has been removed because framework/package metadata must not be represented as the license for the complete mobiST application.
- The project-level proprietary/open-source license decision remains an explicit owner decision. No license terms are invented by this notice.

## PHP/backend runtime

Runtime packages are resolved by `backend/composer.lock`. Material direct/runtime families include:
- Laravel Framework, Inertia Laravel, Predis and Flysystem components: MIT.
- AWS SDK for PHP and AWS CRT PHP: Apache-2.0.
- Transitive packages include MIT, BSD-3-Clause, Apache-2.0 and packages offering multiple permitted licenses. Package-supplied copyright/license files must be preserved when their code is redistributed.

`composer licenses` is the machine-readable authority for the installed Composer graph at a release checkpoint; the lockfile pins exact versions.

## JavaScript runtime

`backend/package-lock.json` currently resolves 10 non-dev runtime packages, all MIT-licensed.

`website/package-lock.json` currently resolves 54 non-dev runtime packages. Material non-MIT entries include:
- `sharp` and platform Sharp packages: Apache-2.0.
- `@img/sharp-libvips-*`: LGPL-3.0-or-later.
- `@swc/helpers`, `baseline-browser-mapping`, `detect-libc`: Apache-2.0.
- `caniuse-lite`: CC-BY-4.0.
- `source-map-js`: BSD-3-Clause.
- `picocolors` and Sharp's nested `semver`: ISC.
- `tslib`: 0BSD.

Only the platform subset actually included by a production deployment is distributed. Release packaging must preserve applicable package-provided license/copyright notices. If a libvips binary is redistributed, the release process must also satisfy the applicable LGPL-3.0-or-later source/relocation obligations rather than stripping or replacing those notices. CC-BY material must retain applicable attribution.

## Assets and tooling

- Canonical `brand/` artwork is treated by project records as first-party approved mobiST artwork; this audit does not independently adjudicate external trademark/copyright ownership.
- No third-party Instrument Sans binary is bundled by the project; the CSS token uses a preferred family name with system fallbacks.
- The ignored local MySQL/Python/dev-tool runtimes are development infrastructure and are not application distribution artifacts. If a future installer or deployment begins redistributing them, their licenses/notices must be audited at that release gate.

## Release rule

Lockfiles and package-provided license files remain authoritative for exact dependency versions/text. Before an external binary/container/package is shipped, MT-7.5/FINAL-AUDIT must compare the actual distribution contents to this notice and carry forward any required license text, attribution, source offer or other distribution obligation.