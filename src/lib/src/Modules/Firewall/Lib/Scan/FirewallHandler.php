<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\{
	Lib\Scan\Handlers\Base,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 15.0
 */
class FirewallHandler extends ExecOnceModConsumer {

	/**
	 * @var false|\WP_Error
	 */
	private $result = false;

	protected function canRun() :bool {
		return ( new CanScan() )
			->setMod( $this->getMod() )
			->run();
	}

	public function getResult() :\WP_Error {
		return is_wp_error( $this->result ) ? $this->result : new \WP_Error();
	}

	protected function run() {
		$this->runScans();

		$result = $this->getResult();
		$block = (bool)apply_filters( 'shield/do_firewall_block', !empty( $result->get_error_codes() ) );
		if ( $block ) {
			$this->doBlock();
		}
	}

	private function runScans() {
		$opts = $this->getOptions();

		$this->result = new \WP_Error();
		foreach ( $this->enumHandlers() as $opt => $handlerInit ) {
			if ( $opts->isOpt( 'block_'.$opt, 'Y' ) ) {
				/** @var Base $handler */
				$handler = $handlerInit();
				$result = $handler->setMod( $this->getMod() )->runCheck();
				if ( !empty( $result->get_error_codes() ) ) {
					$this->result = $result;
					break;
				}
			}
		}
	}

	private function doBlock() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$this->preBlock();

		switch ( $mod->getBlockResponse() ) {
			case 'redirect_die':
				Services::WpGeneral()->wpDie( 'Firewall Triggered' );
				break;
			case 'redirect_die_message':
				Services::WpGeneral()->wpDie( implode( ' ', $this->getFirewallDieMessage() ) );
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

	private function preBlock() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		if ( $opts->isSendBlockEmail() ) {
			$this->getCon()->fireEvent(
				$this->sendBlockEmail() ? 'fw_email_success' : 'fw_email_fail',
				[ 'audit_params' => [ 'to' => $this->getMod()->getPluginReportEmail() ] ]
			);
		}
		$this->getCon()->fireEvent( 'firewall_block', [ 'audit_params' => $this->getResult()->get_error_data() ] );
	}

	protected function getFirewallDieMessage() :array {
		$default = __( "Something in the request URL or Form data triggered the firewall.", 'wp-simple-firewall' );
		$customMessage = $this->getMod()->getTextOpt( 'text_firewalldie' );

		$messages = apply_filters(
			'shield/firewall_die_message',
			[
				empty( $customMessage ) ? $default : $customMessage,
			]
		);
		return is_array( $messages ) ? $messages : [ $default ];
	}

	private function sendBlockEmail() :bool {
		$ip = Services::IP()->getRequestIp();
		$resultData = $this->getResult()->get_error_data( 'shield-firewall' );

		return $this->getMod()
					->getEmailProcessor()
					->sendEmailWithTemplate(
						'/email/firewall_block.twig',
						$this->getMod()->getPluginReportEmail(),
						__( 'Firewall Block Alert', 'wp-simple-firewall' ),
						[
							'strings' => [
								'shield_blocked'  => sprintf( __( '%s Firewall has blocked a request to your WordPress site.', 'wp-simple-firewall' ),
									$this->getCon()->getHumanName() ),
								'details_below'   => __( 'Details for the request are given below:', 'wp-simple-firewall' ),
								'details'         => __( 'Request Details', 'wp-simple-firewall' ),
								'ip_lookup'       => __( 'IP Address Lookup' ),
								'this_is_info'    => __( 'This is for informational purposes only.' ),
								'already_blocked' => sprintf( __( '%s has already taken the necessary action of blocking the request.' ),
									$this->getCon()->getHumanName() ),
							],
							'hrefs'   => [
								'ip_lookup' => add_query_arg( [ 'ip' => $ip ], 'https://shsec.io/botornot' )
							],
							'vars'    => [
								'req_details' => [
									__( 'Visitor IP Address', 'wp-simple-firewall' ) => $ip,
									__( 'Firewall Rule', 'wp-simple-firewall' )      => $resultData[ 'name' ],
									__( 'Request Path', 'wp-simple-firewall' )       => Services::Request()->getPath(),
									__( 'Request Parameter', 'wp-simple-firewall' )  => $resultData[ 'param' ],
									__( 'Request Value', 'wp-simple-firewall' )      => $resultData[ 'value' ],
								]
							]
						]
					);
	}

	/**
	 * @return callable[]
	 */
	private function enumHandlers() :array {
		return [
//			'dir_traversal'    => function () {
//				return new Handlers\DirTraversal();
//			},
//			'sql_queries'      => function () {
//				return new Handlers\SqlQueries();
//			},
//			'wordpress_terms'  => function () {
//				return new Handlers\WpTerms();
//			},
//			'field_truncation' => function () {
//				return new Handlers\FieldTruncation();
//			},
//			'php_code'         => function () {
//				return new Handlers\PhpCode();
//			},
//			'leading_schema'   => function () {
//				return new Handlers\LeadingSchema();
//			},
//			'aggressive'       => function () {
//				return new Handlers\Aggressive();
//			},
'exe_file_uploads' => function () {
	return new Handlers\ExeFiles();
},
		];
	}
}