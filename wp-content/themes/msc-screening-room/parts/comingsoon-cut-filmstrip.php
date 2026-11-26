<?php
$stops = $args['stops'];
if ( count( $stops ) < 2 ) { return; }
?>
<nav class="studio-index cut-filmstrip" aria-label="<?php echo esc_attr( $args['label'] ); ?>">
	<h2><?php echo esc_html( $args['label'] ); ?></h2>
	<ol class="studio-stops" style="--stop-count:<?php echo (int) count( $stops ); ?>">
		<?php $position = 0; foreach ( $stops as $id => $stop ) { ++$position; ?>
			<li><a href="#<?php echo esc_attr( $id ); ?>">
				<?php if ( ! empty( $stop['story'] ) ) {
					msc_cs_preview_art( $stop['story'] );
				} elseif ( ! empty( $stop['image'] ) ) { ?>
					<img src="<?php echo esc_url( $stop['image'] ); ?>" alt="" width="150" height="90" decoding="async">
				<?php } ?>
				<span class="studio-stop-number" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $position ) ); ?></span>
				<span class="studio-stop-label"><?php echo esc_html( $stop['label'] ); ?></span>
			</a></li>
		<?php } ?>
	</ol>
</nav>
