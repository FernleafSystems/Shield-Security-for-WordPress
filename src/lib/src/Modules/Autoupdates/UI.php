<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class UI extends Base\ShieldUI {

	/**
	 * @return array
	 */
	public function getInsightsConfigCardData() {
		/** @var \ICWP_WPSF_FeatureHandler_Autoupdates $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$data = [
			'strings'      => [
				'title' => __( 'Automatic Updates', 'wp-simple-firewall' ),
				'sub'   => __( 'Control WordPress Automatic Updates', 'wp-simple-firewall' ),
			],
			'key_opts'     => [],
			'href_options' => $mod->getUrl_AdminPage()
		];

		if ( !$mod->isModOptEnabled() ) {
			$data[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		elseif ( $opts->isDisableAllAutoUpdates() ) {
			$data[ 'key_opts' ][ 'disabled' ] = [
				'name'    => __( 'Disabled All', 'wp-simple-firewall' ),
				'enabled' => false,
				'summary' => __( 'All automatic updates on this site are disabled', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
			];
		}
		else {
			$bCanCore = Services::WpGeneral()->canCoreUpdateAutomatically();
			$data[ 'key_opts' ][ 'core_minor' ] = [
				'name'    => __( 'Core Updates', 'wp-simple-firewall' ),
				'enabled' => $bCanCore,
				'summary' => $bCanCore ?
					__( 'Minor WP Core updates will be installed automatically', 'wp-simple-firewall' )
					: __( 'Minor WP Core updates will not be installed automatically', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $mod->getUrl_DirectLinkToOption( 'autoupdate_core' ),
			];

			$bHasDelay = $mod->isModOptEnabled() && $opts->getDelayUpdatesPeriod();
			$data[ 'key_opts' ][ 'delay' ] = [
				'name'    => __( 'Update Delay', 'wp-simple-firewall' ),
				'enabled' => $bHasDelay,
				'summary' => $bHasDelay ?
					__( 'Automatic updates are applied after a short delay', 'wp-simple-firewall' )
					: __( 'Automatic updates are applied immediately', 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $mod->getUrl_DirectLinkToOption( 'update_delay' ),
			];

			$sName = $this->getCon()->getHumanName();
			$bSelfAuto = $mod->isModOptEnabled()
						 && in_array( $opts->getSelfAutoUpdateOpt(), [ 'auto', 'immediate' ] );
			$data[ 'key_opts' ][ 'self' ] = [
				'name'    => __( 'Self Auto-Update', 'wp-simple-firewall' ),
				'enabled' => $bSelfAuto,
				'summary' => $bSelfAuto ?
					sprintf( __( '%s is automatically updated', 'wp-simple-firewall' ), $sName )
					: sprintf( __( "%s isn't automatically updated", 'wp-simple-firewall' ), $sName ),
				'weight'  => 1,
				'href'    => $mod->getUrl_DirectLinkToOption( 'autoupdate_plugin_self' ),
			];
		}

		return $data;
	}
}