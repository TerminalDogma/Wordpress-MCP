<?php
namespace MSC;

/** ACF control that retains explicit network media ownership. No bare image IDs. */
final class AcfMediaField extends \acf_field {
	public function __construct() {
		$this->name     = 'msc_media';
		$this->label    = 'Shared network media';
		$this->category = 'content';
		$this->defaults = array( 'msc_multiple' => false );
		parent::__construct();
	}
	public function render_field( $field ) {
		Media::control( $field['name'], $field['id'], $field['value'], ! empty( $field['msc_multiple'] ), $field['msc_name'] ?? '' );
	}
}
