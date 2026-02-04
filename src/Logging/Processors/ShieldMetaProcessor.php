<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Logging\Processors;

class ShieldMetaProcessor extends BaseMetaProcessor {

	/**
	 * @return array
	 */
	public function __invoke( array $record ) {
		$record[ 'extra' ][ 'meta_shield' ] = \array_filter( [
			'offense' => self::con()->comps->offense_tracker->getOffenseCount() > 0 ? 1 : 0,
		] );
		return $record;
	}
}