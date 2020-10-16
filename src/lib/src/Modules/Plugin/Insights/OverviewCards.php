<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Ssl;

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

		$cards = array_merge(
			$cards,
			$this->getNoticesSite()
		);

		$cardSection[ 'cards' ] = $cards;
		return [ 'plugin' => $cardSection ];
	}

	private function getNoticesSite() :array {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $mod */
		$mod = $this->getMod();
		$WP = Services::WpGeneral();
		$srvSSL = new Ssl();
		$cards = [];

		// SSL Expires
		$sHomeUrl = $WP->getHomeUrl();
		$sSiteUrl = $WP->getWpUrl();
		$bHomeSsl = strpos( $sHomeUrl, 'https://' ) === 0;
		$bSiteSsl = strpos( $sSiteUrl, 'https://' ) === 0;

		if ( !$bHomeSsl ) {
			$cards[ 'site_https' ] = [
				'name'    => __( 'HTTPS', 'wp-simple-firewall' ),
				'state'   => -1,
				'summary' => __( "Site visitor traffic isn't protected by HTTPS", 'wp-simple-firewall' ),
				'href'    => $WP->getAdminUrl_Settings(),
				'help'    => __( "It's recommended that an SSL certificate is installed on your site.", 'wp-simple-firewall' )
			];
		}
		elseif ( !$bSiteSsl ) {
			$cards[ 'url_https' ] = [
				'name'    => __( 'HTTPS', 'wp-simple-firewall' ),
				'state'   => -1,
				'summary' => __( "Site visitor traffic isn't protected by HTTPS", 'wp-simple-firewall' ),
				'href'    => $WP->getAdminUrl_Settings(),
				'help'    => __( "HTTPS setting for Home URL and Site URL are not consistent.", 'wp-simple-firewall' )
			];
		}
		elseif ( !$srvSSL->isEnvSupported() ) {
			// If we can't test our SSL certificate, we just assume it's okay.
			$cards[ 'site_https' ] = [
				'name'    => __( 'HTTPS', 'wp-simple-firewall' ),
				'state'   => 1,
				'summary' => __( "Site visitor traffic set to use HTTPS", 'wp-simple-firewall' ),
				'href'    => $WP->getAdminUrl_Settings(),
				'help'    => __( "It's recommended that an SSL certificate is installed on your site.", 'wp-simple-firewall' )
			];
		}
		else {

			try {
				// first verify SSL cert:
				$srvSSL->getCertDetailsForDomain( $sHomeUrl );

				// If we didn't throw an exception, we got it.
				$nExpiresAt = $srvSSL->getExpiresAt( $sHomeUrl );
				if ( $nExpiresAt > 0 ) {
					$nTimeLeft = ( $nExpiresAt - Services::Request()->ts() );
					$bExpired = $nTimeLeft < 0;
					$nDaysLeft = $bExpired ? 0 : (int)round( $nTimeLeft/DAY_IN_SECONDS, 0, PHP_ROUND_HALF_DOWN );

					$cards[ 'site_ssl' ] = [
						'name'    => __( 'SSL Certificate', 'wp-simple-firewall' ),
						'state'   => 1,
						'summary' => __( 'SSL Certificate remains valid for at least the next 2 weeks', 'wp-simple-firewall' ),
						'href'    => $this->getUrlSslCheck(),
						'help'    => __( "It's recommended to keep a valid SSL certificate installed at all times.", 'wp-simple-firewall' )
					];
					if ( $nDaysLeft < 15 ) {

						$cards[ 'site_ssl' ][ 'state' ] = $bExpired ? -2 : -1;

						if ( $bExpired ) {
							$cards[ 'site_ssl' ][ 'summary' ] = __( 'SSL certificate for this site has expired.', 'wp-simple-firewall' );
							$cards[ 'site_ssl' ][ 'help' ] = __( "Renew your SSL certificate.", 'wp-simple-firewall' );
						}
						else {
							$cards[ 'site_ssl' ][ 'summary' ] = sprintf( __( 'SSL certificate will expire soon (%s days)', 'wp-simple-firewall' ), $nDaysLeft );
							$cards[ 'site_ssl' ][ 'help' ] = __( "Check or renew your SSL certificate.", 'wp-simple-firewall' );
						}
					}
				}
			}
			catch ( \Exception $e ) {
				$cards[ 'site_ssl' ] = [
					'name'    => __( 'SSL Certificate', 'wp-simple-firewall' ),
					'state'   => 0,
					'summary' => __( "Couldn't automatically test and verify your site SSL certificate", 'wp-simple-firewall' ),
					'href'    => $this->getUrlSslCheck(),
					'help'    => sprintf( '%s: %s', __( 'Error message', 'wp-simple-firewall' ), $e->getMessage() )
				];
			}
		}

		// db password strength
		$bStrong = ( new \ZxcvbnPhp\Zxcvbn() )->passwordStrength( DB_PASSWORD )[ 'score' ] >= 4;
		$cards[ 'db_strength' ] = [
			'name'    => __( 'DB Password', 'wp-simple-firewall' ),
			'state'   => $bStrong >= 4 ? 1 : -1,
			'summary' => $bStrong ?
				__( 'WP Database password is very strong', 'wp-simple-firewall' )
				: __( "WP Database password appears to be weak", 'wp-simple-firewall' ),
			'href'    => '',
			'help'    => __( 'The database password should be strong.', 'wp-simple-firewall' )
		];

		return $cards;
	}

	private function getUrlSslCheck() :string {
		return add_query_arg(
			[
				'action' => Services::WpGeneral()->getHomeUrl(),
				'run'    => 'toolpage'
			],
			'https://mxtoolbox.com/SuperTool.aspx'
		);
	}
}