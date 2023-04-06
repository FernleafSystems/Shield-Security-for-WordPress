<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Strings {

	use ModConsumer;

	public function getModTagLine() :string {
		return __( $this->getMod()->cfg->properties[ 'tagline' ], 'wp-simple-firewall' );
	}

	/**
	 * @return string[][][]|string[][]
	 */
	public function getEventStrings() :array {
		return [];
	}

	/**
	 * @return array{name: string, summary: string, description: array}
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		$opt = $this->getOptions()->getOptDefinition( $key );
		if ( !empty( $opt[ 'name' ] ) && !empty( $opt[ 'summary' ] ) && !empty( $opt[ 'description' ] ) ) {
			return [
				'name'        => __( $opt[ 'name' ], 'wp-simple-firewall' ),
				'summary'     => __( $opt[ 'summary' ], 'wp-simple-firewall' ),
				'description' => [ __( $opt[ 'description' ], 'wp-simple-firewall' ) ],
			];
		}
		throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $key ) );
	}

	/**
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {

		$section = $this->getOptions()->getSection( $section );
		if ( is_array( $section ) && !empty( $section[ 'title' ] ) && !empty( $section[ 'title_short' ] ) ) {
			$title = __( $section[ 'title' ], 'wp-simple-firewall' );
			$titleShort = __( $section[ 'title_short' ], 'wp-simple-firewall' );
			$summary = empty( $section[ 'summary' ] ) ? [] : $section[ 'summary' ];
		}
		else {
			throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $section ) );
		}

		return [
			'title'       => $title,
			'title_short' => $titleShort,
			'summary'     => ( isset( $summary ) && is_array( $summary ) ) ? $summary : [],
		];
	}
}