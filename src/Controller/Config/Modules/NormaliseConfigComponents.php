<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

class NormaliseConfigComponents {

	public function indexSections( array $sections ) :array {
		$indexed = [];
		foreach ( $sections as $key => $section ) {
			$indexed[ \is_numeric( $key ) ? $section[ 'slug' ] : $key ] = $section;
		}
		return $indexed;
	}

	public function indexOptions( array $options ) :array {
		$indexed = [];
		foreach ( $options as $key => $option ) {
			if ( empty( $option[ 'section' ] ) ) {
				$option[ 'section' ] = 'section_hidden';
			}
			$option[ 'transferable' ] = $option[ 'transferable' ] ?? ( $option[ 'section' ] !== 'section_hidden' );

			$indexed[ \is_numeric( $key ) ? $option[ 'key' ] : $key ] = $option;
		}
		return $indexed;
	}
}