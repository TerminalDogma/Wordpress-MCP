# Multisite Shared Content

A shared TV Show has one permanent identity across a network, while each website has its own native WordPress post ID and URL. Its global premiere date is inherited until an editor explicitly chooses a local override. When the global date changes, inherited sites follow it; overridden sites keep their values. Resetting an override resumes inheritance of the newest date.

Local descriptions, images, SEO text, and themes are independent. The plugin supplies data and an optional block, not a network-wide page design. Authorized editors can change global information from any participating website.

- [Editor guide](editor.md)
- [Network administrator guide](administrator.md)
- [Architecture and API reference](developer.md)
- [Functional specification](specification.md)
- [Verification results](verification.md)
- [Troubleshooting](troubleshooting.md)
- [Changelog, limitations, and roadmap](changelog.md)
- [Working integration examples](../examples/)

Global records and revisions survive local deletion, deactivation, and uninstall. Shared pages and archives are **noindex by default**. Pending synchronization does not mean every website has updated.

- [ACF supported-field integration](acf.md)
- [Mac quickstart](../../docs/shared-content-quickstart.md)
