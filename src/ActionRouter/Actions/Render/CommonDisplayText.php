<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

class CommonDisplayText {

	public static function truncate( string $text, int $length = 100 ) :string {
		return \strlen( $text ) > $length ? \substr( $text, 0, $length ).' (...truncated)' : $text;
	}
}
