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
	public static function control( string $name, string $id, $value, bool $multiple = false, string $context = '' ): void {
		$value = is_array( $value ) ? $value : array();
		echo '<div class="msc-media-control" data-multiple="' . ( $multiple ? '1' : '0' ) . '"><input type="hidden" class="msc-media-value" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_json_encode( $value ) ) . '"><div class="msc-media-previews" aria-live="polite">' . self::previews( $value, $multiple, true ) . '</div><button type="button" class="button msc-choose-media">' . ( $multiple ? 'Choose gallery images' : 'Choose image or media' ) . '</button><p class="description">Inherited images can come from another site. Choose a replacement from this site’s media library.</p>';
		if ( MediaImport::allowed() ) { self::web_control( $id, $context ); } echo '</div>';
	}
	private static function web_control( string $id, string $context ): void {
		$uuid = is_string( $_GET['record'] ?? null ) ? sanitize_text_field( $_GET['record'] ) : ''; $r = $uuid ? Plugin::store()->resolved( $uuid ) : null; $title = '';
		if ( $r ) { foreach ( Schema::get( $r['type'] )['fields'] as $key => $field ) { if ( 'post_title' === ( $field['property'] ?? '' ) ) { $title = (string) ( $r['data'][ $key ] ?? '' ); break; } } }
		$kind = array( 'poster' => 'poster', 'headshot' => 'headshot', 'episode_image' => 'episode still', 'promotional_images' => 'promotional image' )[ $context ] ?? 'image'; $query = trim( $title . ' ' . $kind );
		if ( $r && 'msc_game' === $r['type'] && 'poster' === $context ) { $kind = 'game cover'; $query = trim( $title . ' ' . $kind ); }
		echo '<details class="msc-web-image" data-image-kind="' . esc_attr( $kind ) . '"><summary>Google Images / image URL</summary><div class="msc-web-image-search"><label for="' . esc_attr( $id . '-query' ) . '">Search terms<input type="search" class="msc-image-query" id="' . esc_attr( $id . '-query' ) . '" value="' . esc_attr( $query ) . '"></label><a class="button msc-search-google" href="' . esc_url( add_query_arg( array( 'tbm' => 'isch', 'q' => $query ), 'https://www.google.com/search' ) ) . '" target="_blank" rel="noopener noreferrer">Search Google Images ↗</a></div><p class="description">Google Images opens in a new tab. Open the original image, copy its image address, and paste it below.</p><label for="' . esc_attr( $id . '-url' ) . '">Image URL<input type="text" inputmode="url" class="large-text msc-image-url" id="' . esc_attr( $id . '-url' ) . '" placeholder="https://example.com/poster.jpg"></label><details class="msc-image-source"><summary>Source and credit (optional)</summary><label for="' . esc_attr( $id . '-source' ) . '">Source page URL<input type="text" inputmode="url" class="large-text msc-image-source-url" id="' . esc_attr( $id . '-source' ) . '"></label><label for="' . esc_attr( $id . '-credit' ) . '">Image credit<input type="text" class="large-text msc-image-credit-input" id="' . esc_attr( $id . '-credit' ) . '" maxlength="500"></label><p class="description">Source and credit appear in the site footer when this image is displayed.</p></details><button type="button" class="button msc-import-image-url">Import and use image</button><p class="msc-image-import-status" role="status" aria-live="polite"></p><p class="description">The image is added to this site’s library. Save the record to apply it. Maximum 8 MB.</p></details>';
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
