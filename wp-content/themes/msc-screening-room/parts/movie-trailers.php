<?php
$record = $args['record'];
$reference = $record['data']['promotional_images'][0] ?? ( $record['data']['featured_image'] ?? array() );
\MSC\Trailers::render( $record, \MSC\Media::url( (array) $reference, 'medium_large' ) );
