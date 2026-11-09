<?php
namespace MSC;

/** Google Images results through Serper; credentials never leave the server. */
final class ImageSearch {
	public const PAGE_SIZE = 10;
	public static function key(): string {
		return defined( 'MSC_SERPER_API_KEY' ) ? (string) MSC_SERPER_API_KEY : (string) ( getenv( 'SERPER_API_KEY' ) ?: get_network_option( get_current_network_id(), 'msc_image_search_key', '' ) );
	}
	public static function managed(): bool {
		return defined( 'MSC_SERPER_API_KEY' ) || (bool) getenv( 'SERPER_API_KEY' );
	}
	public static function configured(): bool {
		return '' !== self::key();
	}
	public static function save_key( string $key, bool $clear = false ): void {
		if ( ! current_user_can( 'manage_network_options' ) || self::managed() ) {
			throw new SourceError( 'Network permission is required; server-managed keys cannot be changed here.', 403 );
		}
		if ( $clear ) {
			delete_network_option( get_current_network_id(), 'msc_image_search_key' );
			return;
		}
		$key = trim( $key );
		if ( '' === $key ) {
			return;
		}
		if ( ! preg_match( '/^[A-Za-z0-9_-]{16,256}$/D', $key ) ) {
			throw new SourceError( 'Enter a valid Serper API key.' );
		}
		update_network_option( get_current_network_id(), 'msc_image_search_key', $key );
	}
	/** Format validation here avoids DNS requests for every thumbnail. Downloads use MediaImport. */
	private static function link( $url, bool $https = true ): string {
		if ( ! is_string( $url ) || strlen( $url ) > 4096 ) {
			return '';
		}
		$p = wp_parse_url( $url );
		if ( ! $p || ! in_array( $p['scheme'] ?? '', $https ? array( 'https' ) : array( 'http', 'https' ), true ) || empty( $p['host'] ) || isset( $p['user'] ) || isset( $p['pass'] ) || isset( $p['port'] ) && ( 'https' === ( $p['scheme'] ?? '' ) ? 443 : 80 ) !== (int) $p['port'] ) {
			return '';
		}
		$host = strtolower( trim( $p['host'], '[]' ) );
		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) || filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE ) ) {
			return '';
		}
		return esc_url_raw( $url, array( 'http', 'https' ) );
	}
	public static function normalize( array $rows ): array {
		$items = array();
		$seen  = array();
		foreach ( array_slice( $rows, 0, self::PAGE_SIZE ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$image = self::link( $row['imageUrl'] ?? '' );
			if ( ! $image || isset( $seen[ $image ] ) ) {
				continue;
			}
			$seen[ $image ] = true;
			$text           = static fn( $v ) => is_string( $v ) ? mb_substr( sanitize_text_field( $v ), 0, 300 ) : '';
			$items[]        = array(
				'url'        => $image,
				'thumbnail'  => self::link( $row['thumbnailUrl'] ?? '' ),
				'source_url' => self::link( $row['link'] ?? '', false ),
				'title'      => $text( $row['title'] ?? '' ) ?: 'Image',
				'source'     => $text( $row['source'] ?? $row['domain'] ?? '' ),
				'width'      => min( 100000, max( 0, (int) ( $row['imageWidth'] ?? 0 ) ) ),
				'height'     => min( 100000, max( 0, (int) ( $row['imageHeight'] ?? 0 ) ) ),
			);
		}
		return $items;
	}
	public static function search( string $query, int $page = 1, string $kind = 'images' ): array {
		if ( ! in_array( $kind, array( 'images', 'videos' ), true ) ) {
			throw new SourceError( 'Unsupported search type.' );
		}
		if ( ! MediaImport::allowed() ) {
			throw new SourceError( 'Image upload and shared-content editing permission are required.', 403 );
		}
		$label = 'videos' === $kind ? 'YouTube' : 'Image';
		$query = trim( sanitize_text_field( $query ) );
		if ( '' === $query || mb_strlen( $query ) > 160 || $page < 1 || $page > 10 ) {
			throw new SourceError( 'Enter search terms up to 160 characters and choose a page from 1 to 10.' );
		}
		if ( ! self::configured() ) {
			throw new SourceError( 'Configure image search under Network Admin → Shared Content → Image Search.', 503 );
		}
		$fingerprint = hash( 'sha256', self::key() );
		$cache       = ( 'videos' === $kind ? 'msc_trailer_search_' : 'msc_image_search_' ) . hash( 'sha256', $fingerprint . '|' . $query . '|' . $page );
		$cached      = get_site_transient( $cache );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( get_site_transient( 'msc_image_search_backoff_' . $fingerprint ) ) {
			throw new SourceError( $label . ' search is temporarily rate limited. Wait briefly and retry.', 429 );
		}
		Database::lock(
			'image-search-rate:' . get_current_user_id(),
			static function () {
				$key    = 'msc_image_search_rate_' . get_current_user_id();
				$state  = (array) get_site_transient( $key );
				$minute = (int) floor( time() / 60 );
				$count  = ( $state['minute'] ?? 0 ) === $minute ? (int) $state['count'] : 0;
				if ( $count >= 30 ) {
					throw new SourceError( 'Wait a minute before making more image searches.', 429 );
				}
				set_site_transient(
					$key,
					array(
						'minute' => $minute,
						'count'  => $count + 1,
					),
					65
				);
			}
		);
		$response = wp_safe_remote_post(
			'https://google.serper.dev/' . $kind,
			array(
				'timeout'             => 20,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => MB_IN_BYTES,
				'headers'             => array(
					'X-API-KEY'    => self::key(),
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'                => wp_json_encode(
					array(
						'q'    => 'videos' === $kind ? $query . ' site:youtube.com/watch' : $query,
						'page' => $page,
						'num'  => self::PAGE_SIZE,
						'gl'   => 'us',
						'hl'   => 'en',
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			throw new SourceError( $label . ' search could not be reached. Please retry.', 502 );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( in_array( $status, array( 401, 403 ), true ) ) {
			throw new SourceError( $label . ' search was not authorized. Check the Serper key and account credits in Image Search settings.', 503 );
		}
		if ( 429 === $status ) {
			$delay = max( 1, min( 300, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ?: 5 ) );
			set_site_transient( 'msc_image_search_backoff_' . $fingerprint, true, $delay );
			throw new SourceError( $label . ' search is rate limited. Retry after ' . $delay . ' seconds.', 429 );
		}
		if ( 200 !== $status ) {
			throw new SourceError( $label . ' search is unavailable. Please retry.', 502 );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data[ $kind ] ) || ! is_array( $data[ $kind ] ) || ! array_is_list( $data[ $kind ] ) ) {
			throw new SourceError( $label . ' search returned unreadable results. Please retry.', 502 );
		}
		$result = array(
			'query'     => $query,
			'page'      => $page,
			'items'     => ( 'videos' === $kind ? Trailers::results( $data[ $kind ] ) : self::normalize( $data[ $kind ] ) ),
			'next_page' => count( $data[ $kind ] ) >= self::PAGE_SIZE && $page < 10 ? $page + 1 : null,
		);
		set_site_transient( $cache, $result, 20 * MINUTE_IN_SECONDS );
		return $result;
	}
	public static function boot(): void {
		ImageSearchAdmin::boot();
		add_action(
			'wp_ajax_msc_search_images',
			static function () {
				check_ajax_referer( MediaImport::nonce_action(), 'nonce' );
				try {
					if ( ! is_string( $_POST['query'] ?? null ) || ! is_scalar( $_POST['page'] ?? 1 ) || ! preg_match( '/^[1-9][0-9]?$/D', (string) ( $_POST['page'] ?? 1 ) ) ) {
						throw new SourceError( 'Invalid image search request.' );
					}
					wp_send_json_success( self::search( wp_unslash( $_POST['query'] ), (int) ( $_POST['page'] ?? 1 ) ) );
				} catch ( \Throwable $e ) {
					wp_send_json_error( array( 'message' => $e instanceof SourceError ? $e->getMessage() : 'Image search failed. Please retry.' ), $e instanceof SourceError ? $e->status : 502 );
				}
			}
		);
	}
}
