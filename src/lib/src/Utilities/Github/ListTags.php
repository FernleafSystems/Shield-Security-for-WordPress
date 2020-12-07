<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Github;

use FernleafSystems\Wordpress\Services\Services;

class ListTags {

	const BASE_URL = 'https://api.github.com/repos/%s/tags';

	public function run( string $repo ) :array {
		$tags = [];
		$raw = Services::HttpRequest()->getContent( sprintf( self::BASE_URL, $repo ) );
		if ( !empty( $raw ) ) {
			$tags = array_map( function ( $tag ) {
				return $tag[ 'name' ];
			}, json_decode( $raw, true ) );
		}
		return is_array( $tags ) ? $tags : [];
	}
}

