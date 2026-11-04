# Release 1.2.0 verification

WordPress 7.1.4, PHP 8.3.36, MariaDB 11.4 and ACF 6.8.10; three independent subsites.

- 100 core checks, including 205-record batching, passed.
- Nine Apache HTTP checks, including authorization and concurrent saves, passed.
- Non-image media URL check passed.
- 27 real ACF assertions passed.
- Chromium calendar, global save, local preservation and reset workflow passed.
- PHP syntax passed.

See [ACF support and limits](acf.md) and [roadmap](changelog.md). This is a staging/local release candidate. Full production load, all third-party combinations, broad accessibility/Gutenberg browser coverage and physical Mac testing remain unverified. The complete source package includes reproduction scripts and the repository verification report.

The 1.2 catalog suite covers distinct TV/Movie fields, additive upgrades preserving custom schemas, native-property aliases, UUIDs, local records and overrides, plus Movie inheritance, dates, queries and noindex. The browser workflow checks distinct forms and Movie editing across sites. Current runs are recorded in the repository Shared Content workflow.
