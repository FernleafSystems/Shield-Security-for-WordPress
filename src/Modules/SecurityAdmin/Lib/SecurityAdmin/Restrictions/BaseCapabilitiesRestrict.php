<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions;

class BaseCapabilitiesRestrict extends Base {

	public const AREA_SLUG = '';

	protected function canRun() :bool {
		return $this->hasRestrictedCapabilities();
	}

	protected function run() {
		add_filter( 'user_has_cap', [ $this, 'removeCapabilities' ], 0, 3 );
	}

	/**
	 * @param array $allCaps
	 * @param       $cap
	 * @param array $args
	 * @return array
	 */
	public function removeCapabilities( $allCaps, $cap, $args ) {
		/** @var string $requestedCap */
		$requestedCap = $args[ 0 ];

		if ( \is_string( $requestedCap ) && $this->isCapabilityToBeRestricted( $requestedCap ) ) {
			$allCaps[ $requestedCap ] = false;
		}

		return $allCaps;
	}

	protected function getApplicableCapabilities() :array {
		return [];
	}

	protected function getRestrictedCapabilities() :array {
		return self::con()->opts->optGet( 'admin_access_restrict_'.static::AREA_SLUG );
	}

	protected function isCapabilityToBeRestricted( string $cap ) :bool {
		return \in_array( $cap, $this->getApplicableCapabilities() ) && \in_array( $cap, $this->getRestrictedCapabilities() );
	}

	protected function hasRestrictedCapabilities() :bool {
		return !empty( $this->getRestrictedCapabilities() );
	}
}