<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class InstantAlerts extends Base {

	public function title() :string {
		return __( 'Instant Alerts', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Instant alerts on critical events.', 'wp-simple-firewall' );
	}

	public function configureRows() :array {
		return [
			$this->buildConfigureRowInput(
				static::Slug(),
				__( 'Instant Alerts', 'wp-simple-firewall' ),
				EnumEnabledStatus::NEUTRAL,
				__( 'Manage immediate alerts for important security events.', 'wp-simple-firewall' ),
				[],
				$this->buildConfigureRowScope(
					$this->configZoneComponentSlugs(),
					$this->configureRowOptionsForSections( [ 'section_alerts' ] ),
					'',
					__( 'Edit alert settings', 'wp-simple-firewall' )
				)
			),
		];
	}

	public function enabledStatus() :string {
		return EnumEnabledStatus::GOOD;
	}
}
