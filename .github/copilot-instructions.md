# Scouting OpenID Connect Instructions

This repository is a WordPress plugin targeting PHP 8.2. Keep changes narrowly scoped, preserve existing user changes, and follow [`.editorconfig`](../.editorconfig): UTF-8, LF line endings, final newlines, and tabs with a width of four for PHP and JavaScript.

## PHP

- All new or changed PHP must follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) and WordPress PHPDoc conventions.
- Do not use Composer or add Composer files. The workflow installs pinned PHPCS and WPCS without Composer.
- Validate touched PHP with PHP lint and cache-free WPCS. Use `--no-cache` with PHPCS because cached results can reference renamed or deleted files.
- Follow WordPress security conventions: authorize actions, verify nonces where applicable, sanitize input, validate data, and escape output at the point of rendering. Use WordPress localization APIs for user-facing strings.
- Use DocBlocks for files, classes, methods, properties, constants, hooks, and includes when they are introduced or changed. Write useful comments before code; do not add trailing comments or comments that merely restate code.
- Treat Git release history as the source of truth for PHPDoc `@since` values. The first tag identifies the release that introduced a symbol. Use `@since Unreleased Description.` for an unreleased public API change, then replace it before tagging a release.
- Add a second `@since x.y.z Description.` only for meaningful released API or behavior changes.

## JavaScript

- All new or changed JavaScript must follow the [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/) and [JavaScript Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/javascript/).
- Use literal tabs for indentation. Do not replace them with spaces. The workspace JavaScript settings disable indentation detection so formatting preserves this rule.
- Use single-quoted strings, semicolons, strict equality, braces for control blocks, spaced function calls and array access, a space after `!`, no trailing whitespace, and readable line wrapping. Prefer `const` and `let` for new ES2015+ code; do not refactor untouched legacy code only to replace `var`.
- Add a descriptive file-header DocBlock and JSDoc for named functions and significant constants, objects, closures, events, and globals. Include accurate `@since`, `@param`, and `@return` tags where applicable; derive release versions from Git history.
- This repository intentionally has no persistent JavaScript package toolchain. Do not add `package.json`, lockfiles, `node_modules`, ESLint, Prettier, or JSHint unless explicitly requested.

## Validation

- For PHP changes, run the narrowest available PHP lint and cache-free WPCS check before broadening validation.
- For JavaScript changes, run `node --check` on each touched `.js` file when Node.js is available, then confirm tab indentation and the WordPress formatting rules above.
- Run `git diff --check` after edits. Do not claim a check passed unless it was run successfully.
- Keep [`.github/workflows/build-test.yml`](workflows/build-test.yml) aligned with the repository's actual tooling. Do not assume a JavaScript CI job exists.