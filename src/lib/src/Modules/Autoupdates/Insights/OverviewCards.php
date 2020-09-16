<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Autoupdates\Options;
use FernleafSystems\Wordpress\Services\Services;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Autoupdates $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'Automatic Updates', 'wp-simple-firewall' ),
			'subtitle'     => __( 'Controlling WordPress Automatic Updates', 'wp-simple-firewall' ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModOptEnabled() ) {
			$cards[ 'mod' ] = $this->getModDisabledCard();
		}
		else {
			$bCanCore = Services::WpGeneral()->canCoreUpdateAutomatically();
			$cards[ 'core_minor' ] = [
				'name'    => __( 'Core Updates', 'wp-simple-firewall' ),
				'state'   => $bCanCore ? 1 : -1,
				'summary' => $bCanCore ?
					__( 'Minor WP Core updates will be installed automatically', 'wp-simple-firewall' )
					: __( 'Minor WP Core updates will not be installed automatically', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'autoupdate_core' ),
			];

			$bHasDelay = $mod->isModOptEnabled() && $opts->getDelayUpdatesPeriod();
			$cards[ 'delay' ] = [
				'name'    => __( 'Update Delay', 'wp-simple-firewall' ),
				'state'   => $bHasDelay ? 1 : -1,
				'summary' => $bHasDelay ?
					__( 'Automatic updates are applied after a short delay', 'wp-simple-firewall' )
					: __( 'Automatic updates are applied immediately', 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'update_delay' ),
			];

			$sName = $this->getCon()->getHumanName();
			$bSelfAuto = $mod->isModOptEnabled()
						 && in_array( $opts->getSelfAutoUpdateOpt(), [ 'auto', 'immediate' ] );
			$cards[ 'self' ] = [
				'name'    => __( 'Self Auto-Update', 'wp-simple-firewall' ),
				'state'   => $bSelfAuto ? 1 : -1,
				'summary' => $bSelfAuto ?
					sprintf( __( '%s is automatically updated', 'wp-simple-firewall' ), $sName )
					: sprintf( __( "%s isn't automatically updated", 'wp-simple-firewall' ), $sName ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'autoupdate_plugin_self' ),
			];
		}
		{ //really disabled?
			$WP = Services::WpGeneral();
			if ( $mod->isModOptEnabled()
				 && $opts->isDisableAllAutoUpdates() && !$WP->getWpAutomaticUpdater()->is_disabled() ) {
				$notices[ 'messages' ][ 'disabled_auto' ] = [
					'name'    => 'Auto Updates Not Really Disabled',
					'summary' => __( 'Automatic Updates Are Not Disabled As Expected.', 'wp-simple-firewall' ),
					'href'    => $mod->getUrl_DirectLinkToOption( 'enable_autoupdate_disable_all' ),
					'action'  => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Options', 'wp-simple-firewall' ) ),
					'help'    => sprintf( __( 'A plugin/theme other than %s is affecting your automatic update settings.', 'wp-simple-firewall' ), $this->getCon()
																																						->getHumanName() ),
					'state'   => -2
				];
			}
		}

		$cardSection[ 'cards' ] = $cards;
		return [ 'auto_updates' => $cardSection ];
	}
}