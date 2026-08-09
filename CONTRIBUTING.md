# Contributing to Scouting OpenID Connect

Thank you for helping improve the Scouting OpenID Connect WordPress plugin.

## Where to Contribute

- Report reproducible plugin bugs through the [bug report form](https://github.com/Scouting-nl/scouting-openid-connect/issues/new/choose).
- Ask for help through the [support discussions](https://github.com/scouting-nl/scouting-openid-connect/discussions/categories/support).
- Share feature requests and ideas through the [ideas discussions](https://github.com/scouting-nl/scouting-openid-connect/discussions/categories/ideas).
- Report security vulnerabilities privately according to [SECURITY.md](SECURITY.md). Do not open a public issue or discussion for a suspected vulnerability.

Do not include client secrets, access tokens, ID tokens, passwords, personal data, or production logs in issues, pull requests, or discussions.

## Development

Use a WordPress test installation and a client configuration that you are authorized to use. Refer to [README.md](README.md) for the required plugin configuration and supported WordPress and PHP versions.

Keep changes focused and consistent with the existing WordPress coding and security practices. In particular:

- Sanitize input, validate authorization and nonces where applicable, and escape output in its rendering context.
- Preserve the OpenID Connect protections around state, nonce, PKCE, and session handling.
- Update translations when changing user-facing strings.
- Update [README.md](README.md), [CHANGELOG.md](CHANGELOG.md), or other documentation when user-visible behavior or configuration changes.

## PHPDoc Release History

PHPDoc entries use the release history as their source of truth:

- The first `@since` tag on a file, class, method, property, constant, hook, or include identifies the first plugin release that shipped it.
- Add another `@since x.y.z Description.` entry when a released API contract changes, including added, renamed, or newly optional parameters, changed defaults, wrappers around new APIs, or changed expected behavior.
- For a pull request that introduces an unreleased public signature change, add `@since Unreleased Description.`. Replace it with the tagged release version before creating the release tag.
- Review behavior-only changes manually. Git history can detect signatures reliably, but it cannot determine whether every implementation change is behaviorally significant.

## Pull Requests

Before opening a pull request:

- Start from the latest default branch and keep unrelated changes out of the pull request.
- Add or update focused tests when practical, and describe the validation you performed.
- Explain the problem, the chosen solution, and any configuration, upgrade, or compatibility impact.
- Do not commit generated ZIP files, credentials, or environment-specific files.

GitHub Actions runs the WordPress Plugin Check on pushes and pull requests. Ensure that check passes before requesting review.

## Reviews and Releases

Maintainers review pull requests for behavior, security, backward compatibility, and WordPress Plugin Check results. Releases are created by maintainers from version tags; contributors should not prepare release archives unless requested.
