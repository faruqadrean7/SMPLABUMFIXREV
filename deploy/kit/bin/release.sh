#!/usr/bin/env bash
# Activate a release that was already uploaded to <app>/releases/<id>.
# Usage: release.sh <app> <release-id> [--skip-build]
set -euo pipefail
source "$(dirname "$0")/../lib/common.sh"

app="${1:-}"
rel="${2:-}"
skip_build=0
[[ "${3:-}" == "--skip-build" ]] && skip_build=1

[[ -n "$app" && -n "$rel" ]] || { echo "usage: release.sh <app> <release-id> [--skip-build]" >&2; exit 2; }
[[ "$rel" =~ ^[A-Za-z0-9._-]{1,64}$ ]] || die "invalid release id: $rel"

require_app "$app"
lock_app "$app"

root="$(app_dir "$app")"
rel_dir="$root/releases/$rel"
[[ -d "$rel_dir" ]] || die "release not found: $rel_dir"

log "activating $app -> $rel"

# 1. Link shared resources (never overwritten by a deploy).
mkdir -p "$root/shared"
for item in .env storage; do
  if [[ -e "$root/shared/$item" && ! -e "$rel_dir/$item" ]]; then
    ln -s "$root/shared/$item" "$rel_dir/$item"
    log "linked shared/$item"
  fi
done

# 2. Build step.
if [[ $skip_build -eq 0 && -f "$rel_dir/composer.json" ]] && command -v composer >/dev/null 2>&1; then
  log "composer install"
  ( cd "$rel_dir" && composer install --no-dev --prefer-dist --no-interaction \
      --optimize-autoloader --no-progress )
fi
if [[ $skip_build -eq 0 && -f "$rel_dir/package.json" ]] && command -v npm >/dev/null 2>&1 \
   && grep -q '"build"' "$rel_dir/package.json"; then
  log "npm ci && npm run build"
  ( cd "$rel_dir" && npm ci --omit=dev --no-audit --no-fund && npm run build )
fi

# 3. Project-specific hooks.
run_hook "$rel_dir" pre-activate

# 4. Remember the outgoing release, then swap atomically.
if [[ -L "$root/current" ]]; then
  readlink -f "$root/current" > "$root/.previous" || true
fi
swap_current "$root" "$rel_dir"
log "current -> $rel"

run_hook "$rel_dir" post-activate

# 5. Prune old releases (keep newest N, always keep current + previous).
current_real="$(readlink -f "$root/current")"
previous_real="$(cat "$root/.previous" 2>/dev/null || true)"
mapfile -t all < <(find "$root/releases" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r)
kept=0
for name in "${all[@]}"; do
  dir="$root/releases/$name"
  [[ "$dir" == "$current_real" || "$dir" == "$previous_real" ]] && continue
  kept=$((kept + 1))
  if [[ $kept -gt $KEEP_RELEASES ]]; then
    rm -rf "$dir"
    log "pruned $name"
  fi
done

log "done: $app @ $rel"
