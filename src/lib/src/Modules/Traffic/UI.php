<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Traffic\ForTraffic;
use FernleafSystems\Wordpress\Services\Services;

class UI extends BaseShield\UI {

	public function renderTrafficTable() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		return $mod->renderTemplate(
			'/wpadmin_pages/insights/traffic/traffic_table.twig',
			[
				'ajax'    => [
					'traffictable_action' => $mod->getAjaxActionData( 'traffictable_action', true ),
				],
				'flags'   => [
					'is_enabled' => $opts->isTrafficLoggerEnabled(),
				],
				'hrefs'   => [
					'please_enable' => $mod->getUrl_DirectLinkToOption( 'enable_logger' ),
				],
				'strings' => [
				],
				'vars'    => [
					'datatables_init'   => ( new ForTraffic() )
						->setMod( $this->getMod() )
						->build()
				],
			],
			true
		);
	}

	protected function getSectionWarnings( string $section ) :array {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$warning = [];

		$srvIP = Services::IP();
		if ( !$srvIP->isValidIp_PublicRange( $srvIP->getRequestIp() ) ) {
			$warning[] = __( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.', 'wp-simple-firewall' );
		}

		switch ( $section ) {
			case 'section_traffic_limiter':
				if ( $this->getCon()->isPremiumActive() ) {
					if ( !$opts->isTrafficLoggerEnabled() ) {
						$warning[] = sprintf( __( '%s may only be enabled if the Traffic Logger feature is also turned on.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
					}
				}
				else {
					$warning[] = sprintf( __( '%s is a Pro-only feature.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
				}
				break;
		}

		return $warning;
	}
}