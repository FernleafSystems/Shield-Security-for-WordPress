<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Handler extends Base\Handler {

	public function cleanLabel( string $label ) :string {
		return trim( empty( $label ) ? '' : preg_replace( '#[^\s\da-z_-]#i', '', $label ) );
	}
}