#!/usr/bin/env bash
# Roll back to the previous release, or list releases.
# Usage: rollback.sh <app>          # switch to previous
#        rollback.sh <app> --list   # show releases
set -euo pipefail
source "$(dirname "$0")/../lib/common.sh"

app="${1:-}"
require_app "$app"
root="$(app_dir "$app")"

if [[ "${2:-}" == "--list" ]]; then
  current_real="$(readlink -f "$root/current" 2>/dev/null || true)"
  for name in $(find "$root/releases" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r); do
    mark="  "
    [[ "$root/releases/$name" == "$current_real" ]] && mark="* "
    printf '%s%s\n' "$mark" "$name"
  done
  exit 0
fi

lock_app "$app"
[[ -f "$root/.previous" ]] || die "no previous release recorded for $app"
prev="$(cat "$root/.previous")"
[[ -d "$prev" ]] || die "previous release is gone: $prev"

current_real="$(readlink -f "$root/current" 2>/dev/null || true)"
swap_current "$root" "$prev"
[[ -n "$current_real" ]] && printf '%s\n' "$current_real" > "$root/.previous"

log "rolled back $app -> $(basename "$prev")"
run_hook "$prev" post-activate || true
