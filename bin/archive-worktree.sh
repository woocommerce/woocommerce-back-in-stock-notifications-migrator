#!/usr/bin/env bash
# Archive a pinned commit or the current contents of tracked files.
set -euo pipefail

output=$1
prefix=$2
shift 2
if [[ $# -gt 0 ]]; then
    ref=$(git rev-parse --verify --end-of-options "$1^{commit}")
    git archive --prefix="$prefix" --format=zip -o "$output" "$ref"
    exit
fi

scratch=$(mktemp -d)
trap 'rm -rf -- "$scratch"' EXIT

# List changes against HEAD before switching to the temporary index. This
# includes staged additions, worktree edits and deletions.
GIT_OPTIONAL_LOCKS=0 git diff --name-only --no-renames -z HEAD -- > "$scratch/paths"
if [[ ! -s "$scratch/paths" ]]; then
    git archive --worktree-attributes --prefix="$prefix" --format=zip -o "$output" HEAD
    exit
fi

objects=$(git rev-parse --path-format=absolute --git-path objects)
mkdir "$scratch/objects"
export GIT_INDEX_FILE="$scratch/index"
export GIT_OBJECT_DIRECTORY="$scratch/objects"
export GIT_ALTERNATE_OBJECT_DIRECTORIES="$objects${GIT_ALTERNATE_OBJECT_DIRECTORIES:+:$GIT_ALTERNATE_OBJECT_DIRECTORIES}"
git read-tree HEAD
git --literal-pathspecs add -f --pathspec-from-file="$scratch/paths" --pathspec-file-nul
git archive --worktree-attributes --prefix="$prefix" --format=zip -o "$output" "$(git write-tree)"
