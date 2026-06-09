# BMM Registration — Claude Code Rules

## Version bump on every push

**Every commit that is pushed to the remote MUST include a version bump.**

WordPress detects available updates by comparing the `Version:` header in `bmm-registration.php`
against the version on the tracked GitHub branch. If the version is not bumped, WordPress will
not offer the update to the admin.

### Rule

Before running `git push`, always:

1. Increment `Version:` in `bmm-registration.php` (line ~5) following semver:
   - Bug fixes / small changes → patch bump (1.0.0 → 1.0.1)
   - New features → minor bump (1.0.0 → 1.1.0)
   - Breaking changes → major bump (1.0.0 → 2.0.0)

2. Update the `BMM_REG_VERSION` constant on the next line to match exactly.

Example — both of these must stay in sync:
```
 * Version: 1.0.2
...
define( 'BMM_REG_VERSION', '1.0.2' );
```

3. Include the version bump in the same commit as the feature/fix (not a separate commit).

## Tracked branch

The update checker watches the `tevi` branch. Once development stabilises and `tevi` is
merged into `main`, update `setBranch( 'tevi' )` → `setBranch( 'main' )` in
`bmm-registration.php`.
