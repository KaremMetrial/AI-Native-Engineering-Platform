#!/usr/bin/env bash
# Installs the PHPStan PHAR directly from GitHub Releases rather than via
# Composer.
#
# Why: phpstan/phpstan's Packagist listing has no usable VCS/source install
# path -- only a dist zip, served from api.github.com. In environments where
# that host is not reachable (session-scoped GitHub access, restrictive
# egress policy), `composer require phpstan/phpstan` fails outright. The PHAR
# download below comes from github.com's release-assets host, which is a
# different, unauthenticated static-asset path -- and is PHPStan's own
# officially documented alternative installation method, not a workaround
# (https://phpstan.org/user-guide/getting-started).
#
# This also means Larastan and Deptrac are NOT installed by this script --
# Larastan needs phpstan/phpstan as an actual Composer package (its classes
# extend PHPStan's), which the PHAR cannot provide. That gap is tracked
# explicitly, not silently absorbed: see tools/phpstan/README.md.
set -euo pipefail

VERSION="2.2.8"
# Pinned by computing sha256 on a verified-valid download in the environment
# that first fetched it, then checked into source. Full PGP verification
# (a .asc signature is published alongside every release) was not used here
# because it requires a keyserver fetch this environment cannot make either;
# trust-on-first-use with a pinned hash is the practical middle ground.
EXPECTED_SHA256="ab9ea72523fe453b9f4dd19f12b1e403a91efa894cd25d9b0cb3ef62b7d20bf2"

TARGET_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../apps/api" && pwd)"
TARGET="${TARGET_DIR}/.tools/phpstan.phar"

mkdir -p "$(dirname "$TARGET")"

echo "Downloading phpstan.phar ${VERSION}..."
curl -sSL --fail -o "$TARGET" \
  "https://github.com/phpstan/phpstan/releases/download/${VERSION}/phpstan.phar"

ACTUAL_SHA256="$(sha256sum "$TARGET" | cut -d' ' -f1)"
if [ "$ACTUAL_SHA256" != "$EXPECTED_SHA256" ]; then
  echo "Checksum mismatch for phpstan.phar ${VERSION}." >&2
  echo "  expected: ${EXPECTED_SHA256}" >&2
  echo "  actual:   ${ACTUAL_SHA256}" >&2
  rm -f "$TARGET"
  exit 1
fi

chmod +x "$TARGET"
echo "phpstan.phar ${VERSION} installed and verified at ${TARGET}"
