<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

use FernleafSystems\Wordpress\Services\Services;

class UserMetaProcessor extends BaseMetaProcessor {

	/**
	 * @return array
	 */
	public function __invoke( array $record ) {
		$record[ 'extra' ][ 'meta_user' ] = \array_filter( [
			'uid' => Services::WpUsers()->getCurrentWpUserId(),
		] );
		return $record;
	}
}