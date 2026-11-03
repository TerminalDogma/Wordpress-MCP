<?php
namespace MSC;
final class Media {
	public static function sanitize( $value ): array {
		if ( empty( $value ) ) { return array(); }
		if ( ! is_array( $value ) || empty( $value['site_id'] ) || empty( $value['attachment_id'] ) ) { throw new \InvalidArgumentException( 'Media needs site_id and attachment_id.' ); }
		$site = get_site( (int) $value['site_id'] );
		if ( ! $site || (int) $site->network_id !== get_current_network_id() ) { throw new \InvalidArgumentException( 'Media owner must be in this network.' ); }
		switch_to_blog( (int) $value['site_id'] );
		try {
			$post = get_post( (int) $value['attachment_id'] );
			if ( ! $post || 'attachment' !== $post->post_type || ! current_user_can( 'read_post', $post->ID ) ) { throw new \InvalidArgumentException( 'Media attachment is not readable.' ); }
			return array( 'site_id' => (int) $value['site_id'], 'attachment_id' => (int) $value['attachment_id'] );
		} finally { restore_current_blog(); }
	}
	public static function url( array $ref, string $size = 'large' ): string {
		if ( empty( $ref['site_id'] ) || empty( $ref['attachment_id'] ) ) { return (string) apply_filters( 'msc_media_fallback_url', '', $ref ); }
		$site = get_site( (int) $ref['site_id'] );
		if ( ! $site || (int) $site->network_id !== get_current_network_id() || $site->deleted || $site->archived ) { return (string) apply_filters( 'msc_media_fallback_url', '', $ref ); }
		switch_to_blog( (int) $ref['site_id'] );
		try { $url = wp_get_attachment_image_url( (int) $ref['attachment_id'], $size ); if ( ! $url && get_post_type( (int) $ref['attachment_id'] ) === 'attachment' ) { $url = wp_get_attachment_url( (int) $ref['attachment_id'] ); } }
		finally { restore_current_blog(); }
		return $url ?: (string) apply_filters( 'msc_media_fallback_url', '', $ref );
	}
}
