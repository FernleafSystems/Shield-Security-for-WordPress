<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class MeterIpBlocking extends MeterBase {

	const SLUG = 'ips';

	protected function title() :string {
		return __( 'IP Blocking', 'wp-simple-firewall' );
	}

	protected function buildComponents() :array {
		$mod = $this->getCon()->getModule_IPs();
		/** @var IPs\Options $opts */
		$opts = $mod->getOptions();

		return [
			'auto_block'         => [
				'title'            => __( 'Auto IP Block', 'wp-simple-firewall' ),
				'desc_protected'   => sprintf( __( 'Auto IP blocking is turned on with an offense limit of %s.', 'wp-simple-firewall' ),
					$opts->getOffenseLimit() ),
				'desc_unprotected' => __( 'Auto IP blocking is turned of as there is no offense limit provided.', 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'transgression_limit' ),
				'protected'        => $opts->isEnabledAutoBlackList(),
				'weight'           => 50,
			],
			'ade'                => [
				'title'            => __( 'Anti-Bot Detection Engine', 'wp-simple-firewall' ),
				'desc_protected'   => __( 'Anti-Bot Detection Engine is enabled with a minimum bot-score threshold.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( 'Anti-Bot Detection Engine is disabled as there is no minimum bot-score threshold provided.', 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'antibot_minimum' ),
				'protected'        => $opts->isEnabledAntiBotEngine(),
				'weight'           => 30,
			],
			'track_404'          => [
				'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), '404s' ),
				'desc_protected'   => __( 'Bots that trigger 404 errors are penalised.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Bots that trigger 404 errors aren't penalised.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'track_404' ),
				'protected'        => $opts->getOffenseCountFor( 'track_404' ) > 0,
				'weight'           => 20,
			],
			'track_loginfail'    => [
				'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Failed Logins', 'wp-simple-firewall' ) ),
				'desc_protected'   => __( 'Bots that attempt to login and fail are penalised.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Bots that attempt to login and fail aren't penalised.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'track_loginfailed' ),
				'protected'        => $opts->getOffenseCountFor( 'track_loginfailed' ) > 0,
				'weight'           => 30,
			],
			'track_logininvalid' => [
				'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Invalid Logins', 'wp-simple-firewall' ) ),
				'desc_protected'   => __( 'Bots that attempt to login with non-existent usernames are penalised.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Bots that attempt to login with non-existent usernames aren't penalised.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'track_logininvalid' ),
				'protected'        => $opts->getOffenseCountFor( 'track_logininvalid' ) > 0,
				'weight'           => 40,
			],
			'track_xml'          => [
				'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), 'XML-RPC' ),
				'desc_protected'   => __( 'Bots that attempt to access XML-RPC are penalised.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Bots that attempt to access XML-RPC aren't penalised.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'track_xmlrpc' ),
				'protected'        => $opts->getOffenseCountFor( 'track_xmlrpc' ) > 0,
				'weight'           => 40,
			],
			'track_fake'         => [
				'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Fake Web Crawlers', 'wp-simple-firewall' ) ),
				'desc_protected'   => __( 'Currently penalising fake web crawlers.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( 'Not currently penalising fake web crawlers.', 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'track_fakewebcrawler' ),
				'protected'        => $opts->getOffenseCountFor( 'track_fakewebcrawler' ) > 0,
				'weight'           => 30,
			],
			'track_cheese'       => [
				'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Link-Cheese', 'wp-simple-firewall' ) ),
				'desc_protected'   => __( 'Bots that trigger the link-cheese bait are penalised.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Bots that trigger the link-cheese bait aren't penalised.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'track_linkcheese' ),
				'protected' => $opts->getOffenseCountFor( 'track_linkcheese' ) > 0,
				'weight'    => 20,
			],
			'track_script'       => [
				'title'            => sprintf( '%s - %s', __( 'Bot Tracking', 'wp-simple-firewall' ), __( 'Link-Cheese', 'wp-simple-firewall' ) ),
				'desc_protected'   => __( 'Bots that attempt to access invalid scripts or WordPress files are penalised.', 'wp-simple-firewall' ),
				'desc_unprotected' => __( "Bots that attempt to access invalid scripts or WordPress files aren't penalised.", 'wp-simple-firewall' ),
				'href'             => $mod->getUrl_DirectLinkToOption( 'track_invalidscript' ),
				'protected' => $opts->getOffenseCountFor( 'track_invalidscript' ) > 0,
				'weight'    => 20,
			],
		];
	}
}