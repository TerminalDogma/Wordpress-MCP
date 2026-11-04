<?php
namespace MSC;
final class Media {
	public static function preview( array $ref, bool $removable = false ): string {
		if ( empty( $ref['site_id'] ) || empty( $ref['attachment_id'] ) ) { return ''; }
		$url = self::url( $ref, 'medium' );
		if ( ! $url ) { return '<figure class="msc-media-card" data-site="' . (int) $ref['site_id'] . '" data-attachment="' . (int) $ref['attachment_id'] . '"><figcaption>Asset unavailable<small>Library: site ' . (int) $ref['site_id'] . '</small></figcaption>' . ( $removable ? '<button type="button" class="button msc-remove-media">Remove</button>' : '' ) . '</figure>'; }
		$image = false; $title = 'Media';
		switch_to_blog( (int) $ref['site_id'] );
		try { $image = wp_attachment_is_image( (int) $ref['attachment_id'] ); $title = get_the_title( (int) $ref['attachment_id'] ) ?: 'Media'; }
		finally { restore_current_blog(); }
		return '<figure class="msc-media-card" data-site="' . (int) $ref['site_id'] . '" data-attachment="' . (int) $ref['attachment_id'] . '"><a href="' . esc_url( self::url( $ref, 'full' ) ) . '" target="_blank" rel="noopener">' . ( $image ? '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $title ) . '">' : '<span class="dashicons dashicons-media-default" aria-hidden="true"></span>' ) . '</a><figcaption>' . esc_html( $title ) . '<small>Library: site ' . (int) $ref['site_id'] . '</small></figcaption>' . ( $removable ? '<button type="button" class="button msc-remove-media" aria-label="Remove ' . esc_attr( $title ) . '">Remove</button>' : '' ) . '</figure>';
	}
	public static function previews( $value, bool $multiple = false, bool $removable = false ): string {
		$refs = $multiple ? (array) $value : ( empty( $value ) ? array() : array( $value ) );
		$html = ''; foreach ( $refs as $ref ) { if ( is_array( $ref ) ) { $html .= self::preview( $ref, $removable ); } }
		return $html ?: '<p class="msc-media-empty">No media selected.</p>';
	}
	public static function control( string $name, string $id, $value, bool $multiple = false ): void {
		$value = is_array( $value ) ? $value : array();
		echo '<div class="msc-media-control" data-multiple="' . ( $multiple ? '1' : '0' ) . '"><input type="hidden" class="msc-media-value" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_json_encode( $value ) ) . '"><div class="msc-media-previews" aria-live="polite">' . self::previews( $value, $multiple, true ) . '</div><button type="button" class="button msc-choose-media">' . ( $multiple ? 'Choose gallery images' : 'Choose image or media' ) . '</button><p class="description">Inherited images can come from another site. Choose a replacement from this site’s media library.</p></div>';
	}
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
