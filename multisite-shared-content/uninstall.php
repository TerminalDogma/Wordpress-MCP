<?php
/** Uninstall preserves all network records, local posts, overrides, and revisions. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
// No destructive uninstall path. Use an independently reviewed, backed-up DBA migration to purge.
