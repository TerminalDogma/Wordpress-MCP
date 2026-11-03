<?php
/** A different site's template for the identical shared show. No shared layout is imposed. */
$r = msc_from_post( get_the_ID() );
if ( ! $r || 'publish' !== get_post_status() || 'active' !== $r['state'] ) { return; }
?>
<h1><?php echo esc_html( msc_get_field( $r['uuid'], 'title' ) ); ?> — Episode schedule</h1>
<table>
	<thead><tr><th>Season</th><th>Episode</th><th>Title</th><th>Release date</th></tr></thead>
	<tbody>
	<?php foreach ( msc_episodes( $r['uuid'] ) as $id ) : $episode = msc_get_record( $id ); $data = $episode['data']; ?>
		<tr><td><?php echo esc_html( $data['season_number'] ?? '' ); ?></td><td><?php echo esc_html( $data['episode_number'] ?? '' ); ?></td>
		<td><a href="<?php echo esc_url( get_permalink( msc_local_post_id( $id ) ) ); ?>"><?php echo esc_html( $data['title'] ?? '' ); ?></a></td>
		<td><?php echo esc_html( $data['release_date'] ?? '' ); ?></td></tr>
	<?php endforeach; ?>
	</tbody>
</table>
