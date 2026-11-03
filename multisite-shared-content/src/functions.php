<?php
/** Trusted PHP API. Use the permissioned Store methods for mutations. IDs accept UUIDs or internal IDs. */
function msc_get_record( $id, ?int $site = null ): ?array { return \MSC\Plugin::store()->resolved( $id, $site ); }
function msc_get_field( $id, string $field, ?int $site = null ) { return msc_get_record( $id, $site )['data'][ $field ] ?? null; }
function msc_get_global( $id, string $field ) { return \MSC\Plugin::store()->get( $id )['data'][ $field ] ?? null; }
function msc_is_overridden( $id, string $field, ?int $site = null ): bool { $r = msc_get_record( $id, $site ); return $r && array_key_exists( $field, $r['overrides'] ); }
function msc_from_post( int $post_id, ?int $site = null ): ?array { return \MSC\Plugin::store()->by_post( $post_id, $site ); }
function msc_local_post_id( $id, ?int $site = null ): ?int { $r = msc_get_record( $id, $site ); return ! empty( $r['mapping']['post_id'] ) ? (int) $r['mapping']['post_id'] : null; }
function msc_media_url( array $ref, string $size = 'large' ): string { return \MSC\Media::url( $ref, $size ); }
function msc_query( string $type, array $filters = array(), ?int $site = null, int $limit = 50, int $after = 0 ): array { return \MSC\Plugin::store()->query( $type, $filters, $site ?? get_current_blog_id(), $limit, $after ); }
function msc_related( $id, string $field, ?int $site = null ): ?array { $target = msc_get_field( $id, $field, $site ); return $target ? msc_get_record( $target, $site ) : null; }
function msc_episodes( string $show, ?int $site = null, int $limit = 100 ): array { return \MSC\Relationships::episodes( $show, null, $site ?? get_current_blog_id(), $limit ); }
function msc_upcoming_episodes( ?string $show = null, ?string $from = null, ?int $site = null, int $limit = 20 ): array { return \MSC\Relationships::episodes( $show, $from ?? wp_date( 'Y-m-d' ), $site ?? get_current_blog_id(), $limit, true ); }
function msc_next_episode( string $show ): ?array { $ids = msc_upcoming_episodes( $show, null, null, 1 ); return $ids ? msc_get_record( $ids[0] ) : null; }
function msc_seasons( string $show, ?int $site = null ): array { return \MSC\Relationships::seasons( $show, $site ?? get_current_blog_id() ); }
