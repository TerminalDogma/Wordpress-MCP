<footer class="cs-footer">
	<div class="cs-frame">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cs-brand"><img src="<?php echo esc_url( get_template_directory_uri() . '/assets/network/logo-a.svg' ); ?>" width="154" height="43" alt="ComingSoon home" loading="lazy"></a>
		<p><?php echo esc_html( $args['preview_note'] ?? 'Fictional stories and original artwork for this homepage preview.' ); ?></p>
	</div>
	<?php if ( ! empty( $args['sources'] ) ) { ?><div id="msc-source-footer" class="cs-frame"><?php echo \MSC\Attribution::footer(); ?></div><?php } ?>
</footer>
<?php wp_footer(); ?>
</body>
</html>
