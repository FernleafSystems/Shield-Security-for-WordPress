<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateByIpViewBuilder;

class IpAnalysis extends OffCanvasBase {

	public const SLUG = 'offcanvas_ipanalysis';

	protected function buildCanvasTitle() :string {
		return __( 'Investigate IP', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		$renderData = ( new InvestigateByIpViewBuilder() )->build( (string)$this->action_data[ 'ip' ] );
		$renderData[ 'ui' ] = [
			'show_lookup_with_subject' => true,
			'change_label'             => '',
		];

		return self::con()
				   ->comps
				   ->render
				   ->setTemplate( '/wpadmin/components/investigate/ip_body.twig' )
				   ->setData( $renderData )
				   ->render();
	}
}
