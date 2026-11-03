=== Multisite Shared Content ===
Contributors: terminaldogma
Tags: multisite, custom-post-types, shared-content
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Network-owned TV Shows, Episodes, and extensible CPTs with explicit local overrides.

== Installation ==
Upload the release ZIP, network activate, open Network Admin > Shared Content, and enable types for participating sites. Configure capabilities and a real queue runner. See docs/administrator.md.

== Description ==
Authoritative UUID records live in network tables. A durable queue materializes native posts. Override row existence preserves blank, zero, false, and empty lists. Global and local editing are separate.

Sites retain themes, URLs, publication settings, and SEO metadata. Shared pages and archives default to noindex and exclusion from core sitemaps.

== Frequently Asked Questions ==
= Does uninstall delete my data? =
No. Deactivation and uninstall preserve all data. Permanent purge is a separate DBA operation.

= Does it require ACF? =
No. ACF persistence adapters, repeaters, and third-party nested fields are not supported in this release.

= Are changes immediate everywhere? =
The resolved PHP API reads current authoritative values. Native posts and site indexes update asynchronously. Monitor the queue and configure a system cron.

== Changelog ==
= 1.0.0 =
Initial implementation; see docs/changelog.md for limitations and staging requirements.
