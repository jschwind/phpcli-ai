#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")/assets" || { echo "Failed to cd into tests/assets"; exit 1; }

mkdir -p ../result

# Generate outputs
../../runAIProject.sh --tree --config=../config/ai-all.json         ../result/ai-all.txt
../../runAIProject.sh --tree --config=../config/ai-test.json        ../result/ai-test.txt
../../runAIProject.sh --tree --config=../config/ai-test-php.json    ../result/ai-test-php.txt
../../runAIProject.sh --tree --config=../config/ai-test--php.json   ../result/ai-test--php.txt
../../runAIProject.sh --tree --config=../config/ai-test-txt.json    ../result/ai-test-txt.txt
../../runAIProject.sh --tree --config=../config/ai-include-relative.json ../result/ai-include-relative.txt

# Compare with expected fixtures
STATUS=0
for name in ai-all ai-test ai-test-php ai-test--php ai-test-txt ai-include-relative; do
  echo "Checking $name..."
  if diff -u "../expected/$name.txt" "../result/$name.txt"; then
    echo "✅ $name: OK"
  else
    echo "❌ $name: MISMATCH"
    STATUS=1
  fi
  echo
done

exit $STATUS
