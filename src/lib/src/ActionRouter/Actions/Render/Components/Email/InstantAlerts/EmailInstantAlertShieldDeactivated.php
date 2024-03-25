<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;

class EmailInstantAlertShieldDeactivated extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_shield_deactivated';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					__( 'The Shield plugin has just been deactivated.', 'wp-simple-firewall' )
				],
				'outro' => [
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		$alertGroups = [];
		foreach ( \array_filter( $this->action_data[ 'alert_data' ] ) as $alertKey => $alertItems ) {
			$alertGroups[ $alertKey ] = [
				'title' => 'Plugin Deactivation Details',
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
		return [
				   'user' => __( 'User', 'wp-simple-firewall' ),
				   'ip'   => __( 'IP Address', 'wp-simple-firewall' ),
				   'path' => __( 'Request Path', 'wp-simple-firewall' ),
				   'time' => __( 'Timestamp', 'wp-simple-firewall' ),
			   ][ $key ];
	}
}