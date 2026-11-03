<?php
namespace MSC;
final class Permissions {
	public const CAPS = array( 'msc_manage_schemas', 'msc_create_global', 'msc_edit_global', 'msc_override_local', 'msc_publish_local', 'msc_trash_global' );
	public static function require( string $cap, ?string $type = null ): void {
		if ( ! current_user_can( $cap ) || ( $type && ! Schema::enabled( $type ) ) ) { throw new \RuntimeException( 'Permission denied or type is not enabled on this site.' ); }
	}
	public static function install_roles(): void {
		$role = get_role( 'administrator' );
		if ( $role ) { foreach ( self::CAPS as $cap ) { $role->add_cap( $cap ); } }
		// Editors get local work only; network administrators may grant global capabilities explicitly.
		$editor = get_role( 'editor' );
		if ( $editor ) { $editor->add_cap( 'msc_override_local' ); $editor->add_cap( 'msc_publish_local' ); }
	}
	public static function ensure_roles(): void {
		if ( ! get_option( 'msc_roles_version' ) ) { self::install_roles(); update_option( 'msc_roles_version', MSC_VERSION ); }
	}
}
