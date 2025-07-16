#!/bin/bash

MODE="dump"
OUTPUT_FILE=""
CONFIG_PATH=""
POSITIONAL_ARGS=()

for arg in "$@"; do
  case $arg in
    --tree)
      MODE="tree"
      ;;
    --config=*)
      CONFIG_PATH="$arg"
      ;;
    *)
      POSITIONAL_ARGS+=("$arg")
      ;;
  esac
done

if [ ${#POSITIONAL_ARGS[@]} -ge 1 ]; then
  OUTPUT_FILE="${POSITIONAL_ARGS[0]}"
else
  OUTPUT_FILE="ai.txt"
fi

SCRIPT_DIR=$(dirname "$(realpath "$0")")
AI_SCRIPT="$SCRIPT_DIR/ai.php"

if [ ! -f "$AI_SCRIPT" ]; then
  echo "❌ ai.php not found in $SCRIPT_DIR"
  exit 1
fi

OUTPUT_PATH="$PWD/$OUTPUT_FILE"

CMD="php \"$AI_SCRIPT\""

if [ "$MODE" = "tree" ]; then
  CMD="$CMD --tree"
fi

if [ -n "$CONFIG_PATH" ]; then
  CMD="$CMD $CONFIG_PATH"
fi

CMD="$CMD \"$OUTPUT_PATH\""

eval "$CMD"