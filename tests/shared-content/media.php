<?php
wp_set_current_user( 1 );
switch_to_blog( 2 );
try {
	$bits = wp_upload_bits( 'msc-media-document.txt', null, 'Shared document fixture' );
	if ( $bits['error'] ) { throw new RuntimeException( $bits['error'] ); }
	$id = wp_insert_attachment( array( 'post_title' => 'Shared document', 'post_mime_type' => 'text/plain', 'post_status' => 'inherit' ), $bits['file'] );
	$ref = \MSC\Schema::sanitize( array( 'type' => 'media' ), array( 'site_id' => 2, 'attachment_id' => $id ) );
	if ( msc_media_url( $ref ) !== wp_get_attachment_url( $id ) ) { throw new RuntimeException( 'Non-image media reference URL failed.' ); }
	WP_CLI::success( 'Non-image media references resolve the owning attachment URL.' );
} finally { restore_current_blog(); }
