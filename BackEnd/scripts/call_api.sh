#!/usr/bin/env bash
# Wrapper for testing this project's local Flask dev server only.
# The base URL is fixed in this script (not passed as an argument), so
# granting Bash permission to run this wrapper can never be used to reach
# any host other than the local dev server, even if arguments are attacker-
# or injection-controlled.
set -euo pipefail

BASE_URL="http://127.0.0.1:5000"

if [ "$#" -lt 1 ]; then
  echo "Usage: call_api.sh <path> [curl options...]" >&2
  echo "Example: call_api.sh /admin/members -b cookies.txt" >&2
  exit 1
fi

path="$1"
shift

curl -s "$@" "${BASE_URL}${path}"
