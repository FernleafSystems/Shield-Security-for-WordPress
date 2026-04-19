<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\ContainerRenderer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateByIpViewBuilder {

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
	 *     lookup_helper:string
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
	 *     lookup_ajax_attr:string,
	 *     lookup_shortcuts:list<array{
	 *       key:string,
	 *       href:string,
	 *       label:string,
	 *       action_type:string,
	 *       icon_class:string
	 *     }>,
	 *     offcanvas_history_mode:string,
	 *     subject_header:array{}|array{
	 *       title:string,
	 *       meta:string,
	 *       context_step_json:string
	 *     },
	 *   },
	 *   display:array{
	 *     show_subject_header:bool,
	 *     show_lookup_with_subject:bool,
	 *     change_label:string
	 *   },
	 *   content:array{
	 *     ip_analysis:string
	 *   }
	 * }
	 */
	public function build( string $lookup, array $display = [] ) :array {
		$lookup = \trim( sanitize_text_field( $lookup ) );
		$hasLookup = $lookup !== '';
		$hasSubject = $hasLookup && Services::IP()->isValidIp( $lookup );
		$display = $this->normalizeLookupDisplayContract( $display );
		$lookupAjax = $this->buildLookupAjaxContract( 'ip', 3 );

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
			],
			'vars'    => [
				'analyse_ip'      => $lookup,
				'lookup_route'    => $this->buildLookupRouteContract( PluginNavs::SUBNAV_ACTIVITY_BY_IP ),
				'lookup_behavior' => $this->buildLookupBehaviorContract( true, true, true ),
				'lookup_ajax'     => $lookupAjax,
				'lookup_ajax_attr' => $this->buildLookupAjaxAttrValue( $lookupAjax ),
				'lookup_shortcuts' => $this->buildLookupShortcuts(),
				'offcanvas_history_mode' => '',
				'subject_header'  => $hasSubject
					? $this->buildSubjectHeaderContract( $lookup, '', $this->buildResolvedContextStepJson( $lookup ) )
					: [],
			],
			'display' => $display,
			'content' => [
				'ip_analysis' => $hasSubject
					? ( new ContainerRenderer() )->render( $lookup, (bool)$display[ 'show_lookup_with_subject' ] )
					: '',
			],
		];
	}

	/**
	 * @return list<array<string,string>>
	 */
	private function buildLookupShortcuts() :array {
		$currentIp = \trim( (string)self::con()->this_req->ip );
		if ( $currentIp === '' || !Services::IP()->isValidIp( $currentIp ) ) {
			return [];
		}

		return [
			$this->buildLookupShortcutContract(
				'self',
				self::con()->plugin_urls->investigateByIp( $currentIp ),
				__( 'Look up yourself', 'wp-simple-firewall' ),
				'navigate',
				'bi bi-globe2'
			),
		];
	}

	private function buildResolvedContextStepJson( string $ip ) :string {
		$subject = PluginNavs::investigateLandingSubjectDefinitions()[ 'ip' ];

		return OperatorChromeContract::encodeJson( OperatorChromeContract::normalizeStep( [
			'breadcrumb_label' => $ip,
			'title'            => $ip,
			'summary'          => __( 'Review sessions, activity, and request history for this IP address.', 'wp-simple-firewall' ),
			'focus'            => $subject[ 'context_focus' ],
			'next_step'        => __( 'Use the tabs to switch between sessions, activity, and recent traffic.', 'wp-simple-firewall' ),
			'icon_class'       => $subject[ 'icon_class' ],
			'badge'            => $subject[ 'context_badge' ],
			'badge_status'     => $subject[ 'status' ],
			'color_key'        => PluginNavs::MODE_INVESTIGATE,
		] ) );
	}
}
