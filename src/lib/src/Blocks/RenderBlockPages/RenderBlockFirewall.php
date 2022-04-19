<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\QueryRemainingOffenses;
use FernleafSystems\Wordpress\Services\Services;

class RenderBlockFirewall extends BaseBlockPage {

	protected function getPageSpecificData() :array {
		$con = $this->getCon();
		return [
			'strings' => [
				'page_title' => sprintf( '%s | %s', __( 'Request Blocked by Firewall', 'wp-simple-firewall' ), $con->getHumanName() ),
				'title'      => __( 'Request Blocked', 'wp-simple-firewall' ),
				'subtitle'   => __( 'Firewall terminated the request because it triggered a firewall rule.', 'wp-simple-firewall' ),
				'message'    => $this->getFirewallDieMessage(),
			],
		];
	}

	protected function getRestrictionDetailsBlurb() :array {
		$messages = apply_filters( 'shield/firewall_die_message', [
			__( "Data scanned in this request matched at least 1 firewall rule and is considered potentially dangerous.", 'wp-simple-firewall' )
		] );
		return array_merge(
			parent::getRestrictionDetailsBlurb(),
			is_array( $messages ) ? $messages : []
		);
	}

	protected function getRestrictionDetailsPoints() :array {
		$aux = $this->getAuxData();
		/** @var Firewall\Strings $str */
		$str = $this->getMod()->getStrings();

		$remainingOffenses = max( 0, ( new QueryRemainingOffenses() )
			->setMod( $this->getCon()->getModule_IPs() )
			->setIP( Services::IP()->getRequestIp() )
			->run() );

		return array_merge(
			[
				__( 'Remaining Offenses Allowed', 'wp-simple-firewall' ) => $remainingOffenses,
				__( 'Firewall Rule Category', 'wp-simple-firewall' )     => $str->getFirewallCategoryName( $aux[ 'match_category' ] ),
				__( 'Request Parameter', 'wp-simple-firewall' )          => $aux[ 'match_request_param' ],
				__( 'Request Parameter Value', 'wp-simple-firewall' )    => $aux[ 'match_request_value' ],
				__( 'Firewall Pattern', 'wp-simple-firewall' )           => $aux[ 'match_pattern' ],
			],
			parent::getRestrictionDetailsPoints()
		);
	}

	private function getFirewallDieMessage() :array {
		$mod = $this->getCon()->getModule_Firewall();
		$default = __( "Something in the request URL or Form data triggered the firewall.", 'wp-simple-firewall' );
		$customMessage = $mod->getTextOpt( 'text_firewalldie' );

		$messages = apply_filters(
			'shield/firewall_die_message',
			[
				empty( $customMessage ) ? $default : $customMessage,
			]
		);
		return is_array( $messages ) ? $messages : [ $default ];
	}

	protected function getTemplateStub() :string {
		return 'firewall_blocked';
	}
}