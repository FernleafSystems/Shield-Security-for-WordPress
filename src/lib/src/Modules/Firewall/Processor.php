<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\PerformScan;
use FernleafSystems\Wordpress\Services\Services;

class Processor extends BaseShield\Processor {

	/**
	 * @var array
	 */
	private $dieMessage;

	/**
	 * @deprecated 12.0
	 */
	protected $aPatterns;

	/**
	 * @var array
	 */
	private $aAuditBlockMessage;

	/**
	 * @deprecated 12.0
	 */
	private $params;

	private $scanner;

	protected function run() {
		if ( $this->getIfDoFirewallBlock() ) {
			// Hooked here to ensure "plugins_loaded" has completely finished as some mailers aren't init'd.
			add_action( 'init', function () {
				$this->doPreFirewallBlock();
				$this->doFirewallBlock();
			}, 0 );
		}
	}

	private function getScanner() :PerformScan {
		if ( !isset( $this->scanner ) ) {
			$this->scanner = ( new PerformScan() )->setMod( $this->getMod() );
		}
		return $this->scanner;
	}

	private function getIfDoFirewallBlock() :bool {
		$scan = $this->getScanner();
		$scan->execute();
		$result = $scan->getCheckResult();
		return (bool)apply_filters(
			'shield/do_firewall_block',
			$result instanceof \WP_Error && !empty( $result->get_error_codes() )
		);
	}

	private function doPreFirewallBlock() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isSendBlockEmail() ) {
			$recipient = $this->getMod()->getPluginReportEmail();
			$this->getCon()->fireEvent(
				$this->sendBlockEmail( $recipient ) ? 'fw_email_success' : 'fw_email_fail',
				[ 'audit_params' => [ 'recipient' => $recipient ] ]
			);
		}
		$this->getCon()->fireEvent( 'firewall_block' );
	}

	private function doFirewallBlock() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		switch ( $mod->getBlockResponse() ) {
			case 'redirect_die':
				Services::WpGeneral()->wpDie( 'Firewall Triggered' );
				break;
			case 'redirect_die_message':
				Services::WpGeneral()->wpDie( $this->getFirewallDieMessageForDisplay() );
				break;
			case 'redirect_home':
				Services::Response()->redirectToHome();
				break;
			case 'redirect_404':
				header( 'Cache-Control: no-store, no-cache' );
				Services::WpGeneral()->turnOffCache();
				Services::Response()->sendApache404();
				break;
			default:
				break;
		}
		die();
	}

	private function sendBlockEmail( string $recipient ) :bool {
		$ip = Services::IP()->getRequestIp();
		$resultData = $this->getScanner()->getCheckResult()->get_error_data( 'shield-firewall' );

		$message = array_merge(
			[
				sprintf( __( '%s has blocked a page visit to your site.', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() ),
				__( 'Log details for this visitor are below:', 'wp-simple-firewall' ),
				'- '.sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $ip ),
			],
			array_map(
				function ( $line ) {
					return '- '.$line;
				},
				[
					sprintf( __( 'Firewall Trigger: %s.', 'wp-simple-firewall' ), $resultData[ 'name' ] ),
					__( 'Page parameter failed firewall check.', 'wp-simple-firewall' ),
					sprintf( __( 'The offending parameter was "%s" with a value of "%s".', 'wp-simple-firewall' ),
						$resultData[ 'param' ], $resultData[ 'value' ] )
				]
			),
			[
				'',
				sprintf( __( 'You can look up the offending IP Address here: %s', 'wp-simple-firewall' ), 'http://ip-lookup.net/?ip='.$ip )
			]
		);

		return $this->getMod()
					->getEmailProcessor()
					->sendEmailWithWrap(
						$recipient,
						__( 'Firewall Block Alert', 'wp-simple-firewall' ),
						$message
					);
	}

	/**
	 * @deprecated 12.0
	 */
	private function getIfPerformFirewallScan() :bool {
		return false;
	}

	/**
	 * @deprecated 12.0
	 */
	private function isVisitorRequestPermitted() :bool {
		return true;
	}

	/**
	 * @deprecated 12.0
	 */
	protected function doPassCheckBlockExeFileUploads() :bool {
		return true;
	}

	/**
	 * @deprecated 12.0
	 */
	private function doPassCheck( string $blockKey ) :bool {
		return true;
	}

	protected function getFirewallPatterns( $key = null ) {
		return [];
	}

	protected function getFirewallDieMessage() :array {
		if ( !isset( $this->dieMessage ) || !is_array( $this->dieMessage ) ) {
			$this->dieMessage = [ $this->getMod()->getTextOpt( 'text_firewalldie' ) ];
		}
		return $this->dieMessage;
	}

	protected function getFirewallDieMessageForDisplay() :string {
		$default = __( "Something in the request URL or Form data triggered the firewall.", 'wp-simple-firewall' );
		$customMessage = $this->getMod()->getTextOpt( 'text_firewalldie' );
		$messages = apply_filters(
			'shield/firewall_die_message',
			[
				empty( $customMessage ) ? $default : $customMessage,
			]
		);
		return implode( ' ', is_array( $messages ) ? $messages : [ $default ] );
	}

	/**
	 * @param string $msg
	 * @return $this
	 */
	protected function addToFirewallDieMessage( string $msg ) {
		$messages = $this->getFirewallDieMessage();
		$messages[] = $msg;
		$this->dieMessage = $messages;
		return $this;
	}

	/**
	 * @deprecated 12.0
	 */
	private function getParamsToCheck() :array {
		return [];
	}

	/**
	 * @deprecated 12.0
	 */
	private function getRawRequestParams() :array {
		return [];
	}

	/**
	 * @deprecated 12.0
	 */
	private function getFirewallBlockKeyName( string $blockKey ) :string {
		return '';
	}
}