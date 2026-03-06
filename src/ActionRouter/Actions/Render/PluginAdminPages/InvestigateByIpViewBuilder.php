<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container as IpAnalyseContainer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByIpViewBuilder {

	use PluginControllerConsumer;
	use InvestigateRenderContracts;

	/**
	 * @return array{
	 *   flags:array{
	 *     has_lookup:bool,
	 *     has_subject:bool
	 *   },
	 *   hrefs:array{
	 *     by_ip:string
	 *   },
	 *   strings:array{
	 *     lookup_label:string,
	 *     lookup_placeholder:string,
	 *     lookup_submit:string,
	 *     lookup_helper:string,
	 *     no_subject_title:string,
	 *     no_subject_text:string
	 *   },
	 *   vars:array{
	 *     analyse_ip:string,
	 *     lookup_route:array{
	 *       page:string,
	 *       nav:string,
	 *       nav_sub:string
	 *     },
	 *     lookup_behavior:array{
	 *       panel_form:bool,
	 *       use_select2:bool,
	 *       auto_submit_on_change:bool
	 *     },
	 *     lookup_ajax:array{
	 *       subject:string,
	 *       minimum_input_length:int,
	 *       delay_ms:int,
	 *       action:array<string,mixed>
	 *     },
	 *     subject_header:array{}|array{
	 *       title:string,
	 *       meta:string
	 *     }
	 *   },
	 *   content:array{
	 *     ip_analysis:string
	 *   }
	 * }
	 */
	public function build( string $lookup, bool $renderInlineTabs = false ) :array {
		$lookup = \trim( sanitize_text_field( $lookup ) );
		$hasLookup = $lookup !== '';
		$hasSubject = $hasLookup && Services::IP()->isValidIp( $lookup );

		return [
			'flags'   => [
				'has_lookup'  => $hasLookup,
				'has_subject' => $hasSubject,
			],
			'hrefs'   => [
				'by_ip' => self::con()->plugin_urls->investigateByIp(),
			],
			'strings' => [
				'lookup_label'       => __( 'IP Lookup', 'wp-simple-firewall' ),
				'lookup_placeholder' => __( 'Search for an IP address...', 'wp-simple-firewall' ),
				'lookup_submit'      => __( 'Load IP Context', 'wp-simple-firewall' ),
				'lookup_helper'      => __( 'Type at least 3 characters to find matching IP addresses.', 'wp-simple-firewall' ),
				'no_subject_title'   => __( 'No IP Selected', 'wp-simple-firewall' ),
				'no_subject_text'    => __( 'Use the lookup above to load investigate context for an IP address.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'analyse_ip'      => $lookup,
				'lookup_route'    => $this->buildLookupRouteContract( PluginNavs::SUBNAV_ACTIVITY_BY_IP ),
				'lookup_behavior' => $this->buildLookupBehaviorContract( true, true, true ),
				'lookup_ajax'     => $this->buildLookupAjaxContract( 'ip', 3 ),
				'subject_header'  => $hasSubject
					? [
						'title' => $lookup,
						'meta'  => '',
					]
					: [],
			],
			'content' => [
				'ip_analysis' => $hasSubject
					? self::con()->action_router->render( IpAnalyseContainer::class, [
						'ip'                 => $lookup,
						'render_inline_tabs' => $renderInlineTabs,
					] )
					: '',
			],
		];
	}
}
