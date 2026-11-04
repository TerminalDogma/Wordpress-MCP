=== Multisite Shared Content ===
Contributors: terminaldogma
Tags: multisite, custom-post-types, shared-content
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Network-owned TV Shows, Episodes, Movies, People, and extensible CPTs with explicit local overrides.

== Installation ==
Upload the release ZIP, network activate, open Network Admin > Shared Content, and enable types for participating sites. Configure capabilities and a real queue runner. See docs/administrator.md.

== Description ==
Authoritative UUID records live in network tables. A durable queue materializes native posts. Override row existence preserves blank, zero, false, and empty lists. Global and local editing are separate.

Sites retain themes, URLs, publication settings, and SEO metadata. Shared pages and archives default to noindex and exclusion from core sitemaps.

== Frequently Asked Questions ==
= Does uninstall delete my data? =
No. Deactivation and uninstall preserve all data. Permanent purge is a separate DBA operation.

= Does it require ACF? =
No. Optional ACF Free controls support shared scalar fields, single/multiple Person links and owner-aware media previews. ACF Pro repeaters and third-party nested fields are not supported.

= Are changes immediate everywhere? =
The resolved PHP API reads current authoritative values. Native posts and site indexes update asynchronously. Monitor the queue and configure a system cron.

== Changelog ==
= 1.4.0 =
OMDb title search and selective imports, reviewed Person links, owner-aware poster downloads, and secure network API-key configuration. See docs/omdb.md.

= 1.3.0 =
Person records and linked credits, native category taxonomies, image preview controls, and the optional Screening Room demo theme. See docs/presentation.md.

= 1.0.0 =
Initial implementation; see docs/changelog.md for limitations and staging requirements.
