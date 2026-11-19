<?php
$page = $args['page'];
$pages = $args['pages'];
$id = $args['id'];
$key = $args['key'];
$label = $args['label'];
if ( $page <= 1 && $page >= $pages ) { return; }
$page_url = static fn( int $number ): string => ( $number > 1 ? add_query_arg( $key, $number ) : remove_query_arg( $key ) ) . '#' . $id;
?>
<nav class="collection-pagination" aria-label="<?php echo esc_attr( ucfirst( $label ) . ' pagination' ); ?>">
    <?php if ( $page > 1 ) { ?>
        <a class="text-link" href="<?php echo esc_url( $page_url( $pages ? $page - 1 : 1 ) ); ?>">Previous</a>
    <?php } ?>
    <?php if ( $page < $pages ) { ?>
        <a class="collection-more" data-collection-more aria-controls="<?php echo esc_attr( $id ); ?>" href="<?php echo esc_url( $page_url( $page + 1 ) ); ?>">Show more <span class="screen-reader-text"><?php echo esc_html( $label ); ?></span></a>
    <?php } ?>
</nav>
