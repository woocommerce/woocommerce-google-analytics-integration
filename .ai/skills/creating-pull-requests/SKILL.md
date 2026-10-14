---
name: creating-pull-requests
description: Use when asked to create, open or submit a pull request in Google Analytics for WooCommerce, or to push a branch for review. Runs the checks, fills the PR template and the changelog entry, pushes and opens the PR. Triggers on "create a PR", "open a PR", "push and create a PR", "submit for review". Do not run gh pr create directly.
---

# Creating pull requests

Never run `gh pr create` without this workflow.

## Workflow

1. **Read `.github/pull_request_template.md`.** It may have changed; fill every section in order.
2. **Gather context**: `git log origin/trunk..HEAD`, `git diff origin/trunk...HEAD`, and the Linear issue if the branch names one.
3. **Run the checks** and fix what they report (see `code-quality`):

   ```sh
   npm run qa
   ```

   Also run the PHPUnit or E2E tests that cover the code you changed (see `running-tests`). Commit any formatting fixes before you push.
4. **Changelog**: fill `### Changelog entry`, or plan the `changelog: none` label (see `writing-changelog`).
5. **Push** the branch: `git push -u origin <branch>`.
6. **Open the PR** with `gh pr create -R github.com/woocommerce/woocommerce-google-analytics-integration --base trunk --title "<title>" --body-file <file>`. Open it ready for review unless the user asks for a draft. Add `--label "changelog: none"` when there is no entry.

## Template sections

- **Description** – what changed and why. Link the Linear issue or `Closes #…`, and related PRs. State the BC impact if the PR touches a surface listed in `AGENTS.md` under Backward compatibility.
- **Testing instructions** – numbered steps a reviewer can follow, with the expected result of each.
- **Screenshots** – before and after for visible changes, otherwise `_N/A_`.
- **Code review notes** – only what the diff does not show: trade-offs, risks, context, open questions. If every point could be read from the code, write `_N/A_`.
- **After-merge notes** – anything to do after merging, otherwise `_N/A_`.
- **Tasks** – tick only the boxes that apply.
- **Changelog entry** – keep the heading exact; Woorelease parses it.

## Rules

- PR title: a short sentence that says what the change does.
- No “Generated with Claude Code” or other agent bylines, and no `Co-Authored-By` lines in commits.
- The branch prefix sets the changelog label automatically (`branch-labels.yml`); check it matches the entry.
