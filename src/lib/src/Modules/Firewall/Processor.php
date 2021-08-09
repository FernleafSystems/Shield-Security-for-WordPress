<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\FirewallHandler;
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

	private $firewallHandler;

	protected function run() {
	}

	/**
	 * Firewall checking runs at 'init' because plugins_loaded is too soon as some email handler plugins aren't
	 * initiated.
	 */
	public function onWpInit() {
		$this->getFirewallHandler()->execute();
		if ( $this->getIfDoFirewallBlock() ) {
			$this->doFirewallBlock();
		}
	}

	private function getFirewallHandler() :FirewallHandler {
		if ( !isset( $this->firewallHandler ) ) {
			$this->firewallHandler = ( new FirewallHandler() )->setMod( $this->getMod() );
		}
		return $this->firewallHandler;
	}

	private function getIfDoFirewallBlock() :bool {
		$result = $this->getFirewallHandler()->getResult();
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

		$this->doPreFirewallBlock();

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

	private function sendBlockEmail( string $recipient ) :bool {
		$ip = Services::IP()->getRequestIp();
		$resultData = $this->getFirewallHandler()->getResult()->get_error_data( 'shield-firewall' );

		$message = array_merge(
			[
				sprintf( __( '%s has blocked a page visit to your site.', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName() ),
				__( 'Log details for this visitor are below:', 'wp-simple-firewall' ),
			],
			array_map(
				function ( $line ) {
					return '- '.$line;
				},
				[
					sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $ip ),
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

	protected function getWpHookPriority( string $hook ) :int {
		switch ( $hook ) {
			case 'init':
				$pri = 0;
				break;
			default:
				$pri = parent::getWpHookPriority( $hook );
		}
		return $pri;
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

	/**
	 * @deprecated 12.0
	 */
	protected function getFirewallDieMessage() :array {
		return [ $this->getMod()->getTextOpt( 'text_firewalldie' ) ];
	}

	/**
	 * @param string $msg
	 * @return $this
	 * @deprecated 12.0
	 */
	protected function addToFirewallDieMessage( string $msg ) {
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