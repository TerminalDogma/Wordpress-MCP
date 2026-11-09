<?php
use MSC\{Acf, Plugin, Queue, Schema, NetworkSites, Publication};
if ( ! defined( 'WP_CLI' ) || 'local' !== wp_get_environment_type() || ! function_exists( 'acf_form' ) ) {
	throw new RuntimeException( 'Disposable local ACF network required.' );
}
function pub_check( $ok, string $message ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$GLOBALS['pub_checks'];
	WP_CLI::log( 'PASS: ' . $message );
}
function pub_at( int $site, callable $fn ) {
	switch_to_blog( $site );
	try {
		Plugin::register_posts();
		return $fn();
	} finally {
		restore_current_blog();
	}
}
function pub_form( array $r, string $scope, string $publication, array $fields = array(), array $overrides = array() ): array {
	return array(
		'post_id'       => $r['mapping']['post_id'],
		'nonce'         => wp_create_nonce( 'msc_acf_' . $r['mapping']['post_id'] ),
		'scope'         => $scope,
		'publication'   => $publication,
		'version'       => $r['version'],
		'local_version' => $r['mapping']['local_version'],
		'acf'           => $fields,
		'override'      => $overrides,
	);
}
function pub_reject( callable $fn, string $message ): void {
	try {
		$fn();
	} catch ( Throwable $e ) {
		pub_check( true, $message );
		return;
	}
	throw new RuntimeException( 'FAIL: expected publication rejection: ' . $message );
}
$GLOBALS['pub_checks'] = 0;
wp_set_current_user( 1 );
$sites = array();
$saved = array();
foreach ( get_sites( array( 'number' => 0 ) ) as $site ) {
	$key = NetworkSites::key( $site->path );
	if ( ! $key ) {
		continue;
	}
	$id            = (int) $site->blog_id;
	$sites[ $key ] = $id;
	$saved[ $id ]  = array(
		'types'    => get_blog_option( $id, 'msc_types', array() ),
		'policy'   => MSC\GamePolicy::mode( $id ),
		'children' => get_blog_option( $id, 'msc_episodes_as_children', false ),
	);
}
$store = Plugin::store();
try {
	require __DIR__ . '/network-profiles.php';
	Acf::register();
	pub_at(
		$sites['a'],
		static function () use ( $store, $sites ) {
			$make   = static function () use ( $store, $sites ) {
				$r = $store->create(
					'msc_movie',
					array(
						'title'        => 'ACF publication ' . wp_generate_uuid4(),
						'runtime'      => 90,
						'release_date' => '2099-06-01',
					)
				);
				foreach ( array( 'a', 'b' ) as $key ) {
						Plugin::materializer()->sync( (int) $r['id'], $sites[ $key ] );
				}
				return $store->resolved( $r['id'] );
			};
			$r      = $make();
			$form   = pub_form( $r, 'global', 'site', array( Acf::key( 'msc_movie', 'runtime' ) => 101 ) );
			$result = Acf::save( $form );
			pub_check( array( $sites['a'] ) === $result['publication']['sites'] && 101 === $store->get( $r['id'] )['data']['runtime'], 'ACF saves global fields and publishes only the current site' );
			pub_check( 'publish' === $store->mapping( (int) $r['id'], $sites['a'] )['status'] && 'draft' === $store->mapping( (int) $r['id'], $sites['b'] )['status'], 'Local publication leaves other sites as drafts' );
			$r = $make();
			pub_at(
				$sites['b'],
				static function () use ( $r, $store ) {
					$m = $store->mapping( (int) $r['id'] );
					$store->local( $r['id'], array( 'runtime' => 222 ), array(), (int) $m['local_version'] );
				}
			);
			$result = Acf::save( pub_form( $store->resolved( $r['id'] ), 'global', 'global', array( Acf::key( 'msc_movie', 'runtime' ) => 102 ) ) );
			pub_check( array( $sites['a'], $sites['b'] ) === $result['publication']['sites'], 'Global publication targets enabled Movie sites only' );
			pub_check( 'publish' === $store->mapping( (int) $r['id'], $sites['b'] )['status'] && 222 === $store->resolved( $r['id'], $sites['b'] )['data']['runtime'], 'Global publication preserves the destination’s field overrides' );
			$r = $make();
			pub_at(
				$sites['b'],
				static function () use ( $r, $store ) {
					$m = $store->mapping( (int) $r['id'] );
					$store->local( $r['id'], array(), array(), (int) $m['local_version'], 'removed' );
				}
			);
			$result = Acf::save( pub_form( $store->resolved( $r['id'] ), 'global', 'global' ) );
			pub_check( array( $sites['a'] ) === $result['publication']['sites'] && $store->mapping( (int) $r['id'], $sites['b'] )['suppressed'], 'Global publication retains explicit local removals' );
			$r = $store->create( 'msc_movie', array( 'title' => 'Missing projection ' . wp_generate_uuid4() ) );
			Plugin::materializer()->sync( (int) $r['id'], $sites['a'] );
			Acf::save( pub_form( $store->resolved( $r['id'] ), 'global', 'global' ) );
			pub_check( 'publish' === $store->mapping( (int) $r['id'], $sites['b'] )['status'], 'Global publication creates and publishes a missing eligible projection' );
			$r    = $make();
			$form = pub_form( $r, 'global', 'global', array( Acf::key( 'msc_movie', 'runtime' ) => 999 ) );
			$m    = $store->mapping( (int) $r['id'] );
			$store->local( $r['id'], array(), array(), (int) $m['local_version'], 'draft' );
			$before = $store->get( $r['id'] );
			pub_reject( static fn() => Acf::save( $form ), 'A stale local publication token rejects a global publish' );
			pub_check( $before === $store->get( $r['id'] ) && 'draft' === $store->mapping( (int) $r['id'], $sites['b'] )['status'], 'Rejected publication rolls back global field changes and other-site status changes' );
			$r      = $make();
			$before = $store->get( $r['id'] );
			$fail   = static function ( $data ) use ( $sites ) {
				if ( get_current_blog_id() === $sites['b'] && 'msc_movie' === $data['post_type'] && 'publish' === $data['post_status'] ) {
					throw new RuntimeException( 'Injected publication failure.' );
				}
				return $data;
			};
			add_filter( 'wp_insert_post_data', $fail, 99 );
			try {
				pub_reject( static fn() => Acf::save( pub_form( $r, 'global', 'global', array( Acf::key( 'msc_movie', 'runtime' ) => 555 ) ) ), 'A destination failure rejects the combined save/publish action' );
			} finally {
				remove_filter( 'wp_insert_post_data', $fail, 99 );
			}
			pub_check( $before === $store->get( $r['id'] ) && 'draft' === get_post_status( $store->mapping( (int) $r['id'] )['post_id'] ) && 'draft' === $store->mapping( (int) $r['id'], $sites['b'] )['status'], 'Publication failure rolls back fields and earlier site publications' );
			$r             = $make();
			$before        = $store->get( $r['id'] );
			$form          = pub_form( $r, 'global', 'global' );
			$form['nonce'] = 'invalid';
			pub_reject( static fn() => Acf::save( $form ), 'Publication requires the ACF action nonce' );
			pub_check( $before === $store->get( $r['id'] ), 'Invalid nonce preserves shared content' );
			$r        = $make();
			$inactive = $store->update( $r['id'], array(), (int) $r['version'], 'archived' );
			pub_reject( static fn() => Acf::save( pub_form( $store->resolved( $inactive['id'] ), 'global', 'site' ) ), 'Archived records cannot be published from ACF' );
			$r      = $make();
			$editor = get_user_by( 'login', 'msc-acf-publisher-editor' );
			$uid    = $editor ? $editor->ID : wp_insert_user(
				array(
					'user_login' => 'msc-acf-publisher-editor',
					'user_pass'  => wp_generate_password(),
					'role'       => 'editor',
				)
			);
			add_user_to_blog( get_current_blog_id(), (int) $uid, 'editor' );
			wp_set_current_user( (int) $uid );
			$form = pub_form( $r, 'local', 'global', array( Acf::key( 'msc_movie', 'runtime' ) => 333 ), array( 'runtime' => 1 ) );
			pub_reject( static fn() => Acf::save( $form ), 'Local editors cannot forge global publication' );
			pub_check( ! Publication::global_allowed() && ! $store->overrides( (int) $r['id'] ), 'Denied global publication does not save local field changes' );
			$form['publication'] = 'site';
			Acf::save( $form );
			pub_check( 'publish' === $store->mapping( (int) $r['id'] )['status'] && 333 === $store->resolved( $r['id'] )['data']['runtime'] && 90 === $store->get( $r['id'] )['data']['runtime'], 'A local editor can save an override and publish on this site' );
			wp_set_current_user( 1 );
		}
	);
	pub_at(
		$sites['c'],
		static function () use ( $store, $sites ) {
			$r = $store->create(
				'msc_game',
				array(
					'title'        => 'PC publication ' . wp_generate_uuid4(),
					'platforms'    => array( 'PC (Microsoft Windows)' ),
					'platform_ids' => array( 6 ),
				)
			);
			Plugin::materializer()->sync( (int) $r['id'], $sites['c'] );
			$result = Acf::save( pub_form( $store->resolved( $r['id'] ), 'global', 'global' ) );
			pub_check( array( $sites['c'] ) === $result['publication']['sites'] && ! $store->mapping( (int) $r['id'], $sites['d'] ), 'Global game publication excludes a PC-only title from Site D' );
			$r = $store->create(
				'msc_game',
				array(
					'title'        => 'Sony publication ' . wp_generate_uuid4(),
					'platforms'    => array( 'PlayStation 5' ),
					'platform_ids' => array( 167 ),
				)
			);
			Plugin::materializer()->sync( (int) $r['id'], $sites['c'] );
			$result = Acf::save( pub_form( $store->resolved( $r['id'] ), 'global', 'global' ) );
			pub_check( array( $sites['c'], $sites['d'] ) === $result['publication']['sites'] && 'publish' === $store->mapping( (int) $r['id'], $sites['d'] )['status'], 'A Sony game publishes on both eligible game sites' );
		}
	);
} finally {
	wp_set_current_user( 1 );
	NetworkSites::apply( $saved ); for ( $i = 0; $i < 200 && Queue::status()['pending']; ++$i ) {
		$r = Queue::run( 1000 );
		if ( $r['errors'] || ! $r['processed'] ) {
			throw new RuntimeException( 'Publication fixture cleanup queue failed.' );
		}
	}
}
WP_CLI::success( $GLOBALS['pub_checks'] . ' ACF local/global publication, permission, policy and rollback checks passed.' );
