<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;

class EmailInstantAlertAdmins extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_admins';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					__( 'A change in the constituency of administrators has been detected.', 'wp-simple-firewall' ),
				],
				'outro' => [
					__( 'These changes may be normal and expected, but if anything looks strange, you should review your list of site admins immediately.', 'wp-simple-firewall' )
				],
			],
		] );
	}

	protected function buildAlertGroups() :array {
		$alertGroups = [];
		foreach ( \array_filter( $this->action_data[ 'alert_data' ] ) as $alertKey => $alertItems ) {
			$alertGroups[ $alertKey ] = [
				'title' => sprintf( '%s: %s', __( 'Change Detected', 'wp-simple-firewall' ), $this->titleFor( $alertKey ) ),
				'items' => \array_map(
					static fn( string $item ) => [
						'text' => sprintf( '%s: %s', CommonDisplayStrings::get( 'username' ), $item ),
						'href' => '',
					],
					$alertItems
				)
			];
		}
		return $alertGroups;
	}

	private function titleFor( string $key ) :string {
		return [
				   'added'      => __( 'New Admin(s)', 'wp-simple-firewall' ),
				   'removed'    => __( 'Deleted Admin(s)', 'wp-simple-firewall' ),
				   'user_pass'  => __( 'Password Updated', 'wp-simple-firewall' ),
				   'user_email' => __( 'Email Updated', 'wp-simple-firewall' ),
				   'promoted'   => __( 'Promoted To Admin', 'wp-simple-firewall' ),
				   'demoted'    => __( 'Demoted From Admin', 'wp-simple-firewall' ),
			   ][ $key ];
	}
}
