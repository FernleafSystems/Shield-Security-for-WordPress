<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Services\Services;

class Reporting extends Base {

	public function title() :string {
		return __( 'Reporting', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Manage alert digests and informational status reports.', 'wp-simple-firewall' );
	}

	public function configureRows() :array {
		return [
			$this->buildConfigureRowInput(
				'reporting',
				__( 'Reports', 'wp-simple-firewall' ),
				EnumEnabledStatus::NEUTRAL,
				__( 'Manage alert digest delivery, informational reports, and reporting email settings.', 'wp-simple-firewall' ),
				[],
				$this->buildConfigureRowScope(
					$this->configZoneComponentSlugs(),
					$this->configureRowOptionsForSections( [ 'section_reporting' ] ),
					'',
					__( 'Edit report settings', 'wp-simple-firewall' )
				)
			),
		];
	}

	protected function status() :array {
		$status = parent::status();
		$email = (string)self::con()->opts->optGet( 'block_send_email_address' );
		if ( Services::Data()->validEmail( $email ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
			$status[ 'exp' ][] = __( 'A valid report email address is configured for security reporting.', 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = __( 'No dedicated report email address is configured. A default fallback will be used.', 'wp-simple-firewall' );
		}
		return $status;
	}
}
