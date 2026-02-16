<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Zones;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Base;

class SecurityZoneComponent extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_security_zone_component';
	public const TEMPLATE = '/components/zones/components/security_zone_component_%s.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$comp = $this->getZoneComponent();
		$explanation = $comp->explanation();
		return [
			'flags'   => [
			],
			'imgs'    => [
				'svgs' => [
					'status_good'            => $con->svgs->raw( 'shield-check' ),
					'status_okay'            => $con->svgs->raw( 'shield-fill-exclamation' ),
					'status_bad'             => $con->svgs->raw( 'shield-fill-x' ),
					'status_neutral'         => $con->svgs->raw( 'shield' ),
					'status_neutral_enabled' => $con->svgs->raw( 'shield-check' ),
					'explanation_info'       => $con->svgs->raw( 'exclamation-triangle' ),
					'gear'                   => $con->svgs->raw( 'gear' ),
				],
			],
			'strings' => [
				'title'        => $comp->title(),
				'subtitle'     => $comp->subtitle(),
				'explanation'  => $explanation,
				'status_label' => $this->getStatusLabel( $comp->enabledStatus(), $explanation ),
				'configure'    => __( 'Configure', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'enabled_status' => $comp->enabledStatus(),
				'actions'        => $comp->getActions(),
			],
		];
	}

	private function getStatusLabel( string $status, array $explanations ) :string {
		switch ( $status ) {
			case EnumEnabledStatus::GOOD:
			case EnumEnabledStatus::NEUTRAL_ENABLED:
				return __( 'Active', 'wp-simple-firewall' );
			case EnumEnabledStatus::OKAY:
				return __( 'Warning', 'wp-simple-firewall' );
			case EnumEnabledStatus::BAD:
				$count = \count( $explanations );
				return $count > 0
					? \sprintf( _n( '%s Issue', '%s Issues', $count, 'wp-simple-firewall' ), $count )
					: __( 'Inactive', 'wp-simple-firewall' );
			case EnumEnabledStatus::NEUTRAL:
			default:
				return __( 'Inactive', 'wp-simple-firewall' );
		}
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
	 * @return mixed|Base
	 */
	private function getZoneComponent() {
		return self::con()->comps->zones->getZoneComponent( $this->action_data[ 'zone_component' ] );
	}
}