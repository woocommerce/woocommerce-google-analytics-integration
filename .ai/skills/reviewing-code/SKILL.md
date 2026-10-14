---
name: reviewing-code
description: Use when reviewing a Google Analytics for WooCommerce pull request or local changes. Checks the latest code against the AGENTS.md code rules, backward compatibility, boundaries and the PR's testing instructions. Triggers on "review this PR", "review my changes", "code review", "check this branch".
---

# Reviewing code

Print the review for the user. Never post it to GitHub unless asked.

## 1. Get the latest code

- **PR**: `gh pr view <number> -R github.com/woocommerce/woocommerce-google-analytics-integration --json title,body,headRefName,baseRefName,files,labels,statusCheckRollup`, then `git fetch origin` and `git diff origin/trunk...origin/<branch>`. Never review a stale checkout.
- **Local**: `git status`, `git diff origin/trunk...HEAD`, plus `git diff` and `git diff --cached` for uncommitted work.

Read every changed file in full, not only the hunks. If the PR or branch names a Linear issue, read it (with comments) and check the change does what it asks.

## 2. Check

- **Correctness** – logic errors, missing edge cases, classic vs block storefront differences, events that fire twice or not at all.
- **Security** – input sanitized, output escaped, capability checks (`manage_woocommerce`), nonces on admin actions. No tracking ID or secret in code.
- **Code rules** (`AGENTS.md`) – WordPress Coding Standards, text domain `woocommerce-google-analytics-integration`, JS edited in `assets/js/src/` only, no WooCommerce API newer than `WC_GOOGLE_ANALYTICS_INTEGRATION_MIN_WC_VER` without a check, PHP 7.4 syntax, `@since x.x.x` on new hooks and methods.
- **Backward compatibility** – any change to hooks in `docs/Hooks.md`, `window.wcGoogleAnalyticsIntegration`, stored settings, or public classes and constants must state its impact in the PR description.
- **Boundaries** – no new production Composer dependency, no edits to `assets/js/build/` or `docs/Hooks.md`, CI or dependency changes flagged.
- **Tests** – new behavior has PHPUnit, JS or E2E coverage; tests would fail without the change.
- **PR** – every template section filled or `_N/A_`; testing instructions are steps a reviewer can follow, with expected results; `### Changelog entry` present or `changelog: none` label set; the entry prefix matches the branch prefix.
- **CI** – `gh pr checks <number> -R github.com/woocommerce/woocommerce-google-analytics-integration`.

Only report issues you are sure of after reading the code. Mark anything uncertain as a question. Do not flag standard WordPress or WooCommerce patterns.

## 3. Report

Group findings as **Must fix**, **Should fix** and **Questions**, each with `file:line` and a short reason. End with a one‑line verdict.
