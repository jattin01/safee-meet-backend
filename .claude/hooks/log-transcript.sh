#!/usr/bin/env bash
# Stop hook: exports the session transcript (JSONL) into
# claude-history/transcripts/<session_id>.json as a re-importable JSON array.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
HIST_DIR="$REPO_ROOT/claude-history/transcripts"

mkdir -p "$HIST_DIR"

INPUT="$(cat)"
SESSION_ID=$(jq -r '.session_id // "unknown"' <<< "$INPUT")
TRANSCRIPT_PATH=$(jq -r '.transcript_path // empty' <<< "$INPUT")

[ -n "$TRANSCRIPT_PATH" ] && [ -f "$TRANSCRIPT_PATH" ] || exit 0

OUT_FILE="$HIST_DIR/${SESSION_ID}.json"
LOCK_FILE="$HIST_DIR/.${SESSION_ID}.lock"

exec 200>"$LOCK_FILE"
flock -x 200
TMP="$(mktemp "$HIST_DIR/.${SESSION_ID}.tmp.XXXXXX")"
jq -s '.' "$TRANSCRIPT_PATH" > "$TMP" && mv "$TMP" "$OUT_FILE"
flock -u 200
