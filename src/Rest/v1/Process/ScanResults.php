<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class ScanResults extends ScanBase {

	protected function process() :array {
		$statesToInclude = $this->getWpRestRequest()->get_param( 'filter_item_state' );
		if ( \is_string( $statesToInclude ) ) {
			$statesToInclude = \array_filter( \explode( ',', $statesToInclude ) );
		}

		$findings = self::con()->comps->site_query->scanFindings(
			$this->getWpRestRequest()->get_param( 'scan_slugs' ),
			\is_array( $statesToInclude ) ? $statesToInclude : []
		);
		if ( !$findings[ 'is_available' ] ) {
			throw new ApiException( $findings[ 'message' ] );
		}

		return $findings[ 'results' ];
	}
}
