<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Base;

class SecurityZoneComponent extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_security_zone_component';
	public const TEMPLATE = '/components/zones/components/security_zone_component_%s.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$zonesCon = $con->comps->zones;
		$comp = $this->getZoneComponent();
		return [
			'flags'   => [
			],
			'imgs'    => [
				'svgs' => [
					'status_good' => $con->svgs->raw( 'shield-check' ),
					'status_okay' => $con->svgs->raw( 'shield-fill-exclamation' ),
					'status_bad'  => $con->svgs->raw( 'shield-fill-x' ),
				],
			],
			'strings' => [
				'title'    => $comp->title(),
				'subtitle' => $comp->subtitle(),
			],
			'vars'    => [
				'enabled_status' => $comp->enabledStatus(),
				'actions'        => $comp->getActions(),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'zone_component',
		];
	}

	protected function getRenderTemplate() :string {
		$rend = self::con()->comps->render;
		return sprintf( static::TEMPLATE, $rend->templateExists( sprintf( static::TEMPLATE, $this->getZoneComponent()::Slug() ) ) ? $this->getZoneComponent()::Slug() : 'base' );
	}

	/**
	 * @return Base
	 */
	private function getZoneComponent() {
		return $this->action_data[ 'zone_component' ];
	}
}