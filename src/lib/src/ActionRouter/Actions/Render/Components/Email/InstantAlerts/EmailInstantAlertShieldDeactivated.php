<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;

class EmailInstantAlertShieldDeactivated extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_shield_deactivated';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					sprintf( __( 'The %s plugin has just been deactivated.', 'wp-simple-firewall' ), self::con()->labels->Name )
				],
				'outro' => [
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		$alertGroups = [];
		$labels = CommonDisplayStrings::pick( [ 'user_label', 'ip_address_label', 'request_path_label', 'timestamp_label' ] );
		foreach ( \array_filter( $this->action_data[ 'alert_data' ] ) as $alertKey => $alertItems ) {
			$alertGroups[ $alertKey ] = [
				'title' => __( 'Plugin Deactivation Details', 'wp-simple-firewall' ),
				'items' => [],
			];
			foreach ( $alertItems as $type => $path ) {
				$alertGroups[ $alertKey ][ 'items' ][ $type ] = [
					'text' => sprintf( '%s: <code>%s</code>', $this->titleFor( $type ), $path ),
				];
			}
		}
		return $alertGroups;
	}

	private function titleFor( string $key ) :string {
		$labels = CommonDisplayStrings::pick( [ 'user_label', 'ip_address_label', 'request_path_label', 'timestamp_label' ] );
		return [
			'user' => $labels[ 'user_label' ],
			'ip'   => $labels[ 'ip_address_label' ],
			'path' => $labels[ 'request_path_label' ],
			'time' => $labels[ 'timestamp_label' ],
		][ $key ];
	}
}
