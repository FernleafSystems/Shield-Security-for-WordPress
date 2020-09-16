<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class OverviewCards extends Shield\Modules\Base\Insights\OverviewCards {

	public function build() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $mod */
		$mod = $this->getMod();
		/** @var Shield\Modules\Plugin\Options $opts */
		$opts = $this->getOptions();

		$cardSection = [
			'title'        => __( 'General Settings', 'wp-simple-firewall' ),
			'subtitle'     => sprintf( __( 'General %s Settings', 'wp-simple-firewall' ),
				$this->getCon()->getHumanName() ),
			'href_options' => $mod->getUrl_AdminPage()
		];

		$cards = [];

		if ( !$mod->isModuleEnabled() ) {
			$cards[] = $this->getModDisabledCard();
		}
		else {
			$bHasSupportEmail = Services::Data()->validEmail( $opts->getOpt( 'block_send_email_address' ) );
			$cards[ 'reports' ] = [
				'name'    => __( 'Reporting Email', 'wp-simple-firewall' ),
				'state'   => $bHasSupportEmail ? 1 : -1,
				'summary' => $bHasSupportEmail ?
					sprintf( __( 'Email address for reports set to: %s', 'wp-simple-firewall' ), $mod->getPluginReportEmail() )
					: sprintf( __( 'No reporting address provided - defaulting to: %s', 'wp-simple-firewall' ), $mod->getPluginReportEmail() ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'block_send_email_address' ),
			];

			$cards[ 'editing' ] = [
				'name'    => __( 'Visitor IP Detection', 'wp-simple-firewall' ),
				'state'   => 0,
				'summary' => sprintf( __( 'Visitor IP address source is: %s', 'wp-simple-firewall' ),
					__( $opts->getSelectOptionValueText( 'visitor_address_source' ), 'wp-simple-firewall' ) ),
				'href'    => $mod->getUrl_DirectLinkToOption( 'visitor_address_source' ),
			];

			$bRecap = $mod->getCaptchaCfg()->ready;
			$cards[ 'recap' ] = [
				'name'    => __( 'CAPTCHA', 'wp-simple-firewall' ),
				'state'   => $bRecap ? 1 : -1,
				'summary' => $bRecap ?
					__( 'CAPTCHA keys have been provided', 'wp-simple-firewall' )
					: __( "CAPTCHA keys haven't been provided", 'wp-simple-firewall' ),
				'href'    => $mod->getUrl_DirectLinkToSection( 'section_third_party_captcha' ),
			];
		}

		{// Inactive
			$oWpPlugins = Services::WpPlugins();
			$nCount = count( $oWpPlugins->getPlugins() ) - count( $oWpPlugins->getActivePlugins() );
			$cards[ 'plugins_inactive' ] = [
				'name'    => __( 'Inactive Plugins', 'wp-simple-firewall' ),
				'state'   => $nCount > 0 ? -1 : 1,
				'summary' => $nCount > 0 ?
					sprintf( __( 'There are %s inactive and unused plugins', 'wp-simple-firewall' ), $nCount )
					: __( "There appears to be no unused plugins", 'wp-simple-firewall' ),
				'href'    => Services::WpGeneral()->getAdminUrl_Plugins( true ),
				'help'    => __( 'Unused plugins should be removed.', 'wp-simple-firewall' )
			];
		}

		$nCount = count( $oWpPlugins->getUpdates() );
		$cards[ 'plugins_update' ] = [
			'name'    => __( 'Updates', 'wp-simple-firewall' ),
			'state'   => $nCount > 0 ? -1 : 1,
			'summary' => $nCount > 0 ?
				sprintf( __( 'There are %s plugin updates available to be applied', 'wp-simple-firewall' ), $nCount )
				: __( "All available plugin updates have been applied", 'wp-simple-firewall' ),
			'href'    => Services::WpGeneral()->getAdminUrl_Updates( true ),
			'help'    => __( 'Updates should be applied as early as possible.', 'wp-simple-firewall' ),
		];

		$cardSection[ 'cards' ] = $cards;
		return [ 'plugin' => $cardSection ];
	}
}