# Scouting OpenID Connect Instructions

This repository is a WordPress plugin targeting PHP 8.2. Keep changes narrowly scoped, preserve existing user changes, and follow [`.editorconfig`](../.editorconfig): UTF-8, LF line endings, final newlines, tabs with a width of four for PHP, JavaScript, and CSS, and two spaces for YAML.

## Accessibility

- All new or changed user-facing output and interfaces must follow the [WordPress Accessibility Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/accessibility/) and conform to WCAG 2.2 Level AA.
- Prefer semantic HTML and native controls. Use ARIA only when native semantics are insufficient, and keep accessible names, roles, states, and relationships accurate.
- Make every interaction keyboard operable with a logical focus order and visible focus. Associate labels, instructions, validation errors, and status updates programmatically with the controls or regions they describe.
- Do not convey meaning through color, shape, position, or sound alone. Preserve sufficient contrast and provide appropriate text alternatives for non-text content.

## PHP

- All new or changed PHP must follow the [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) and [PHP Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/).
- Do not use Composer or add Composer files. The workflow installs pinned PHPCS and WPCS without Composer.
- Validate touched PHP with PHP lint and cache-free WPCS. Use `--no-cache` with PHPCS because cached results can reference renamed or deleted files.
- Follow WordPress security conventions: authorize actions, verify nonces where applicable, sanitize input, validate data, and escape output at the point of rendering.
- Use DocBlocks for files, classes, methods, properties, constants, hooks, and includes when they are introduced or changed. Write useful comments before code; do not add trailing comments or comments that merely restate code.
- Use `@since Unreleased Description.` for an unreleased change, then replace it before tagging a release.
- Add a second `@since x.y.z Description.` only for meaningful behavior changes.

## HTML

- All new or changed markup, including HTML emitted by PHP or JavaScript, must follow the [WordPress HTML Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/) and the accessibility requirements above.
- Use lowercase tags and attributes, quote attribute values, close every element correctly, and place exactly one space before the slash in self-closing elements.
- Use tabs to reflect logical structure. In mixed PHP and HTML, align PHP blocks with the surrounding markup and keep opening and closing blocks at the same indentation level.

## CSS

- All new or changed CSS must follow the [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/).
- Use tabs for declarations, place each selector and declaration on its own line, include one space after each property colon, end every declaration with a semicolon, and keep closing braces aligned with their selectors.
- Use lowercase, hyphen-separated, descriptive selectors. Quote attribute selector values with double quotes, and avoid over-qualified selectors or unnecessary specificity.
- Group properties logically in display, positioning, box model, color and typography, then other order. Use lowercase values, unitless zero values, numeric font weights, and shortened lowercase hex colors where possible.

## JavaScript

- All new or changed JavaScript must follow the [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/) and [JavaScript Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/javascript/).
- Use literal tabs for indentation. Do not replace them with spaces. The workspace JavaScript settings disable indentation detection so formatting preserves this rule.
- Use single-quoted strings, semicolons, strict equality, braces for control blocks, spaced function calls and array access, a space after `!`, no trailing whitespace, and readable line wrapping. Prefer `const` and `let` for new ES2015+ code; do not refactor untouched legacy code only to replace `var`.
- Add a descriptive file-header DocBlock and JSDoc for named functions and significant constants, objects, closures, events, and globals. Include accurate `@since`, `@param`, and `@return` tags where applicable; derive release versions from Git history.
- Keep [`eslint.config.mjs`](../eslint.config.mjs) as the only persistent JavaScript tooling file. Do not add `package.json`, lockfiles, committed `node_modules`, Prettier, or JSHint unless explicitly requested; CI installs its pinned ESLint dependencies under `RUNNER_TEMP`.

## GitHub Actions

- All new or changed workflow files must follow the [WordPress GitHub Actions Workflow Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/github-actions/) and use two-space YAML indentation.
- Give each workflow a top-level `permissions: {}` declaration, then grant each job only the permissions it requires.
- Pin every external action in `uses:` to a full commit SHA followed by a version comment. Update the SHA and comment together.
- Pass untrusted GitHub context values through environment variables before using them in `run:` blocks or scripts. Never interpolate user-controlled expressions directly into executable code or write them unsafely to GitHub environment files.
- Avoid `pull_request_target` and `workflow_run` for untrusted code. If either trigger is necessary, do not execute pull request code with secrets and document the safety rationale inline.
- Set `persist-credentials: false` on `actions/checkout` unless authenticated Git operations are required; explicitly enable and explain credential persistence when it is necessary. Do not use caches when building or publishing release artifacts unless their integrity is verified.

## Validation

- For PHP changes, run the narrowest available PHP lint and cache-free WPCS check before broadening validation.
- For JavaScript changes, run the pinned ESLint toolchain from [`.github/workflows/build-test.yml`](workflows/build-test.yml) with [`eslint.config.mjs`](../eslint.config.mjs), then run `node --check` on each touched `.js` file.
- For rendered HTML or interface changes, validate complete markup with the W3C validator when practical and manually check keyboard operation, focus behavior, labels, status and error feedback, text alternatives, and contrast. Automated checks do not replace manual review.
- For CSS changes, manually confirm the WordPress formatting rules and test affected states and layouts above and below relevant responsive breakpoints.
- For workflow changes, run Actionlint and `zizmor .` when available, then resolve correctness and security findings rather than suppressing them without a documented reason.
- Run `git diff --check` after edits. Do not claim a check passed unless it was run successfully.
- Keep [`.github/workflows/build-test.yml`](workflows/build-test.yml) aligned with the repository's actual tooling. Do not assume a JavaScript CI job exists.
