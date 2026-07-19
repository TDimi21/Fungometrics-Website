#!/bin/sh
set -eu

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

secret_pattern='(^|/)\.env($|\.(save|backup|local|production|staging|testing))|\.(zip|tar|tgz|pem|key|p8|p12|mobileprovision)$'
forbidden_pattern='(^|/)(\.env($|\.(save|backup|local|production|staging|testing))|\.git($|/)|node_modules($|/)|vendor($|/)|public/build($|/)|dist($|/)|build($|/)|Pods($|/)|DerivedData($|/))|\.(zip|tar|tgz|pem|key|p8|p12|mobileprovision)$'

forbidden_tracked="$(git ls-files | grep -E "$secret_pattern" || true)"
if [ -n "$forbidden_tracked" ]; then
  echo "Source export refused: forbidden files are tracked." >&2
  printf '%s\n' "$forbidden_tracked" >&2
  exit 1
fi

sha="$(git rev-parse --short HEAD)"
repo_name="$(basename "$repo_root")"
destination="${1:-$(dirname "$repo_root")/${repo_name}-source-${sha}.zip}"

case "$destination" in
  "$repo_root"|"$repo_root"/*)
    echo "Source export must be written outside the repository." >&2
    exit 1
    ;;
esac

git archive --format=zip --output="$destination" HEAD
zip -dq "$destination" 'build/*' 'public/build/*' 'public/vendor/*' 'dist/*' 'Pods/*' 'DerivedData/*' || true

forbidden_export="$(unzip -Z1 "$destination" | grep -E "$forbidden_pattern" || true)"
if [ -n "$forbidden_export" ]; then
  echo "Source export validation failed." >&2
  echo "Delete the rejected archive manually: $destination" >&2
  exit 1
fi

echo "Tracked source exported to $destination"
