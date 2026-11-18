<?php
namespace MSC;

/** Actor and character pairs for the movie's linked cast cards. */
final class CastRoles {
    public static function sanitize( $value ): string {
        if ( null === $value || '' === $value ) { return ''; }
        if ( ! is_string( $value ) || strlen( $value ) > 32768 ) {
            throw new \InvalidArgumentException( 'Cast roles must be text under 32 KB.' );
        }
        return trim( sanitize_textarea_field( $value ) );
    }

    public static function by_actor( string $value ): array {
        $roles = array();
        foreach ( preg_split( '/\R/u', $value, -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $line ) {
            $pair = array_map( 'trim', explode( '|', $line, 2 ) );
            if ( empty( $pair[0] ) || empty( $pair[1] ) ) { continue; }
            $roles[ PersonIdentity::name( $pair[0] ) ] = Presentation::text( $pair[1] );
        }
        return $roles;
    }

    /** Return Wikidata character names and identifiers keyed by cast member. */
    public static function wiki_characters( array $entity ): array {
        $cast = array();
        foreach ( $entity['claims']['P161'] ?? array() as $claim ) {
            $person = $claim['mainsnak']['datavalue']['value']['id'] ?? '';
            if ( ! $person || 'deprecated' === ( $claim['rank'] ?? '' ) ) { continue; }
            foreach ( array( 'P4633', 'P453' ) as $property ) {
                foreach ( $claim['qualifiers'][ $property ] ?? array() as $snak ) {
                    $value = $snak['datavalue']['value'] ?? null;
                    if ( 'P453' === $property ) { $value = $value['id'] ?? ''; }
                    elseif ( is_array( $value ) ) { $value = $value['text'] ?? ''; }
                    if ( is_string( $value ) && '' !== $value ) {
                        $cast[ $person ][ 'P453' === $property ? 'ids' : 'names' ][] = $value;
                    }
                }
            }
        }
        return $cast;
    }

    public static function wiki_lines( array $characters, array $entities, array $names ): string {
        $lines = array();
        foreach ( $names as $person => $name ) {
            $row = $characters[ $person ] ?? array();
            $roles = $row['names'] ?? array();
            if ( ! $roles ) {
                foreach ( $row['ids'] ?? array() as $id ) {
                    $role = $entities[ $id ]['labels']['en']['value'] ?? '';
                    if ( $role ) { $roles[] = $role; }
                }
            }
            if ( $roles ) { $lines[] = $name . ' | ' . implode( ' / ', array_unique( $roles ) ); }
        }
        return self::sanitize( implode( "\n", $lines ) );
    }
}
