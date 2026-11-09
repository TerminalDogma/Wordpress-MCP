<?php
namespace MSC;

/** User-selected web images become ordinary, owner-aware WordPress attachments. */
final class MediaImport {
	public const MAX_BYTES = 8 * MB_IN_BYTES;
	public static function allowed(): bool {
		return current_user_can( 'upload_files' ) && ( current_user_can( 'msc_create_global' ) || current_user_can( 'msc_edit_global' ) || current_user_can( 'msc_override_local' ) );
	}
	public static function nonce_action(): string {
		return 'msc_image_url_' . get_current_blog_id();
	}
	private static function url( string $url, bool $https_only = true ): string {
		if ( strlen( $url ) > 4096 ) {
			throw new \InvalidArgumentException( 'The image URL is too long.' );
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? '', $https_only ? array( 'https' ) : array( 'http', 'https' ), true ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || isset( $parts['port'] ) && ( 'https' === ( $parts['scheme'] ?? '' ) ? 443 : 80 ) !== (int) $parts['port'] || ! wp_http_validate_url( $url ) ) {
			throw new \InvalidArgumentException( $https_only ? 'Use a public HTTPS image URL without a username, password or custom port.' : 'Use a public HTTP or HTTPS source page URL without credentials or a custom port.' );
		}
		$host = trim( $parts['host'], '[]' );
		$ip   = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE ) ) {
			throw new \InvalidArgumentException( 'Use a public HTTPS image URL. Local and reserved network addresses are not supported.' );
		}
		return esc_url_raw( $url, $https_only ? array( 'https' ) : array( 'http', 'https' ) );
	}
	public static function import( string $url, string $title = '', string $source_page = '', string $credit = '' ): array {
		if ( ! self::allowed() ) {
			throw new \RuntimeException( 'Image upload and shared-content editing permission are required.' );
		}
		$url         = self::url( trim( $url ) );
		$source_page = trim( $source_page );
		if ( $source_page ) {
			$source_page = self::url( $source_page, false );
		}
		$title  = mb_substr( sanitize_text_field( $title ), 0, 200 );
		$credit = mb_substr( sanitize_text_field( $credit ), 0, 500 );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( 'msc-image-url' );
		if ( ! $tmp ) {
			throw new \RuntimeException( 'The image download could not be prepared.' );
		}
		try {
			$current = $url;
			for ( $redirect = 0; $redirect <= 3; ++$redirect ) {
				self::url( $current );
				$response = wp_safe_remote_get(
					$current,
					array(
						'timeout'             => 15,
						'redirection'         => 0,
						'stream'              => true,
						'filename'            => $tmp,
						'limit_response_size' => self::MAX_BYTES + 1,
						'sslverify'           => true,
					)
				);
				clearstatcache( true, $tmp );
				if ( is_wp_error( $response ) ) {
					throw new \RuntimeException( 'The image could not be downloaded. Check its direct image URL.' );
				}
				$status = wp_remote_retrieve_response_code( $response );
				if ( in_array( $status, array( 301, 302, 303, 307, 308 ), true ) ) {
					$location = wp_remote_retrieve_header( $response, 'location' );
					if ( ! is_string( $location ) || ! $location || 3 === $redirect ) {
						throw new \RuntimeException( 'The image has too many redirects or no download destination.' );
					}
					$current = self::url( \WP_Http::make_absolute_url( $location, $current ) );
					continue;
				}
				if ( 200 !== $status || ! is_file( $tmp ) || 0 === filesize( $tmp ) ) {
					throw new \RuntimeException( 'The image could not be downloaded. Use the original image file, rather than a search results page.' );
				}
				break;
			}
			if ( filesize( $tmp ) > self::MAX_BYTES ) {
				throw new \InvalidArgumentException( 'Choose an image smaller than 8 MB.' );
			}
			$mime = wp_get_image_mime( $tmp );
			$ext  = array(
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/webp' => 'webp',
				'image/gif'  => 'gif',
				'image/avif' => 'avif',
			)[ $mime ] ?? '';
			$size = @getimagesize( $tmp );
			if ( ! $ext || ! $size || $size[0] > 10000 || $size[1] > 10000 || $size[0] * $size[1] > 20000000 ) {
				throw new \InvalidArgumentException( 'Use a JPEG, PNG, WebP, GIF or AVIF image up to 20 megapixels. Search pages and SVG files cannot be imported.' );
			}
			$name = substr( sanitize_title( $title ?: 'imported-image' ), 0, 100 ) ?: 'imported-image';
			$id   = media_handle_sideload(
				array(
					'name'     => $name . '.' . $ext,
					'tmp_name' => $tmp,
				),
				0,
				$title ?: 'Imported image'
			);
			if ( is_wp_error( $id ) ) {
				throw new \RuntimeException( 'WordPress could not save this image to the media library.' );
			}
			update_post_meta( $id, '_wp_attachment_image_alt', $title );
			update_post_meta(
				$id,
				'_msc_manual_image_source',
				array(
					'image_url'   => $url,
					'source_page' => $source_page,
					'credit'      => $credit,
				)
			);
			return array(
				'site_id'       => get_current_blog_id(),
				'attachment_id' => (int) $id,
			);
		} finally {
			if ( is_file( $tmp ) ) {
				wp_delete_file( $tmp );
			}
		}
	}
	public static function boot(): void {
		add_action(
			'wp_ajax_msc_import_image_url',
			static function () {
				check_ajax_referer( self::nonce_action(), 'nonce' );
				if ( ! self::allowed() ) {
					wp_send_json_error( array( 'message' => 'Image upload and shared-content editing permission are required.' ), 403 );
				}
				try {
					$values = array();
					foreach ( array( 'url', 'title', 'source_page', 'credit' ) as $key ) {
						if ( ! is_string( $_POST[ $key ] ?? '' ) ) {
							throw new \InvalidArgumentException( 'Invalid image import field.' );
						}
						$values[ $key ] = wp_unslash( $_POST[ $key ] ?? '' );
					}
					$ref = self::import( $values['url'], $values['title'], $values['source_page'], $values['credit'] );
					wp_send_json_success(
						array(
							'reference'  => $ref,
							'attachment' => wp_prepare_attachment_for_js( $ref['attachment_id'] ),
						)
					);
				} catch ( \Throwable $e ) {
					wp_send_json_error( array( 'message' => $e->getMessage() ), $e instanceof \InvalidArgumentException ? 400 : 502 );
				}
			}
		);
	}
}
