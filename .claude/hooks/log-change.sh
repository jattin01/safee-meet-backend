#!/usr/bin/env bash
# PostToolUse hook (Edit|Write|NotebookEdit): appends a change record to
# claude-history/changes.json so file edits made by Claude are recoverable later.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
HIST_DIR="$REPO_ROOT/claude-history"
CHANGES_FILE="$HIST_DIR/changes.json"
LOCK_FILE="$HIST_DIR/.changes.lock"

mkdir -p "$HIST_DIR"
[ -f "$CHANGES_FILE" ] || echo '[]' > "$CHANGES_FILE"

INPUT="$(cat)"

TOOL_NAME=$(jq -r '.tool_name // "unknown"' <<< "$INPUT")
SESSION_ID=$(jq -r '.session_id // "unknown"' <<< "$INPUT")
FILE_PATH=$(jq -r '.tool_response.filePath // .tool_input.file_path // .tool_input.notebook_path // "unknown"' <<< "$INPUT")
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

case "$FILE_PATH" in
  "$REPO_ROOT"/*) REL_PATH="${FILE_PATH#"$REPO_ROOT"/}" ;;
  *) REL_PATH="$FILE_PATH" ;;
esac

SUMMARY=$(jq -r '
  if .tool_name == "Write" then "file written (created or fully overwritten)"
  elif .tool_name == "NotebookEdit" then "notebook cell edited"
  elif (.tool_input.old_string != null) then "string replaced in file"
  else "file edited"
  end
' <<< "$INPUT")

ENTRY=$(jq -n \
  --arg ts "$TIMESTAMP" \
  --arg sid "$SESSION_ID" \
  --arg tool "$TOOL_NAME" \
  --arg path "$REL_PATH" \
  --arg summary "$SUMMARY" \
  '{timestamp: $ts, session_id: $sid, tool: $tool, file: $path, summary: $summary}')

exec 200>"$LOCK_FILE"
flock -x 200
TMP="$(mktemp "$HIST_DIR/.changes.tmp.XXXXXX")"
jq --argjson entry "$ENTRY" '. + [$entry]' "$CHANGES_FILE" > "$TMP" && mv "$TMP" "$CHANGES_FILE"
flock -u 200
