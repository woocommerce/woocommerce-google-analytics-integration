---
name: writing-changelog
description: Use when a Google Analytics for WooCommerce pull request needs a changelog entry, or when deciding whether it needs one. Covers the "### Changelog entry" section of the PR template and the "changelog: none" label. Triggers on "add a changelog entry", "does this need a changelog", "write the changelog".
---

# Writing the changelog entry

The repo has no changelog files. Woorelease reads the `### Changelog entry` section, the last section of `.github/pull_request_template.md`, when it builds `changelog.txt` and `readme.txt` for a release.

## Format

One line per change, after the `>` line, starting with a type prefix:

```
### Changelog entry

> Fix - Report Composite Products with their catalog price.
```

| Prefix | Use for |
|---|---|
| `Add` | A new feature, function or functionality |
| `Update` | A big change to something that was not broken |
| `Fix` | Something that was not working |
| `Tweak` | A small change of little importance |
| `Dev` | A developer‑facing change only |
| `Doc` | Customer or developer documentation |
| `Break` | A change that breaks earlier API or functionality |

- Write for store owners: what changed for them, not how the code changed.
- Keep the heading `### Changelog entry` exact. If it is removed, Woorelease uses the PR title as the entry.
- The prefix should match the branch prefix label (see `starting-branch`).

## No entry

When the PR needs no entry (CI, tests, internal tooling that is not worth a `Dev` line), add the `changelog: none` label. PRs with that label are left out of the GitHub release notes too (`.github/release.yml`).
