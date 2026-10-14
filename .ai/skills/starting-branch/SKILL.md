---
name: starting-branch
description: Use when starting work on a task or a Linear issue in Google Analytics for WooCommerce and a new branch is needed. Names the branch and creates it from trunk. Triggers on "start a branch", "create a branch for STOMAIL-1234", "start working on this issue". Do not run git switch -c or git checkout -b directly.
---

# Starting a branch

## 1. Pick the name

Format: `<prefix>/<team-key>-<id>-<slug>`, all lowercase, e.g. `fix/stomail-7713-php-8-5-compatibility`.

- **Prefix** – the `branch-labels.yml` workflow turns it into the PR’s changelog label, so pick the one that matches the change:

  | Prefix | Label |
  |---|---|
  | `add/` | `changelog: add` |
  | `update/` | `changelog: update` |
  | `fix/` | `changelog: fix` |
  | `tweak/` | `changelog: tweak` |
  | `dev/` | `changelog: dev` |
  | `doc/` | `changelog: doc` |
  | `breaking/` | `changelog: breaking` |

  `release/` is only for release branches (`release/x.y.z`); they get `changelog: none`.
- **Team key and ID** – from the Linear issue. Fetch it with the Linear MCP tool (`get_issue`) for the title and type. Take the team key from the issue or from recent branch names (`gh pr list -R github.com/woocommerce/woocommerce-google-analytics-integration --state all --limit 20 --json headRefName`); do not guess it.
- **Slug** – short kebab-case summary of the issue title.

Without an issue, drop `<team-key>-<id>-` and suggest a name from the task.

Always show the name and ask for confirmation before creating it.

## 2. Create it

```sh
git fetch origin
git switch -c <branch-name> origin/trunk
```

Check `git status` first. Do not carry unrelated uncommitted changes onto the new branch.
