#!/usr/bin/env bash
# Shared helpers for the release deployer.
set -euo pipefail

DEPLOY_ROOT="${DEPLOY_ROOT:-/var/www}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

log() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*" >&2; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

app_dir() { printf '%s/%s' "$DEPLOY_ROOT" "$1"; }

require_app() {
  local app="${1:-}"
  [[ -n "$app" ]] || die "app name required"
  [[ "$app" =~ ^[a-z0-9][a-z0-9_-]{0,31}$ ]] || die "invalid app name: $app"
  [[ -d "$(app_dir "$app")/releases" ]] || die "no such app under $DEPLOY_ROOT: $app"
}

# One deploy at a time per app. Lock fd 9 held for the rest of the script.
lock_app() {
  local app="$1"
  exec 9>"$(app_dir "$app")/.deploy.lock"
  flock -w 300 9 || die "another deploy is already running for $app"
}

# Symlink swap that is atomic on the same filesystem.
swap_current() {
  local root="$1" target="$2" tmp
  tmp="$root/.current.$$"
  ln -sfn "$target" "$tmp"
  mv -Tf "$tmp" "$root/current"
}

run_hook() {
  local rel_dir="$1" name="$2" hook
  for hook in "$rel_dir/deploy/hooks/$name" "$rel_dir/deploy/hooks/$name.sh" \
              "$rel_dir/.deploy/hooks/$name" "$rel_dir/.deploy/hooks/$name.sh"; do
    if [[ -x "$hook" ]]; then
      log "hook: $(basename "$hook")"
      ( cd "$rel_dir" && "$hook" ) || die "hook failed: $hook"
      return 0
    fi
  done
  return 0
}
