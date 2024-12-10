<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts;

use FernleafSystems\Wordpress\Services\Services;

class EmailInstantAlertFileLocker extends EmailInstantAlertBase {

	public const SLUG = 'email_instant_alert_filelocker';

	protected function getBodyData() :array {
		return Services::DataManipulation()->mergeArraysRecursive( parent::getBodyData(), [
			'strings' => [
				'intro' => [
					__( 'FileLocker has detected changes to critical files.', 'wp-simple-firewall' )
					.' '.__( 'Please take urgent action to review these changes.', 'wp-simple-firewall' )
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
				'title' => 'File Locker Changes Detected',
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
				   'wpconfig'        => __( 'WP Config', 'wp-simple-firewall' ),
				   'theme_functions' => __( 'Theme functions.php', 'wp-simple-firewall' ),
				   'root_htaccess'   => __( 'Root .htaccess', 'wp-simple-firewall' ),
				   'root_index'      => __( 'Root index.php', 'wp-simple-firewall' ),
				   'root_webconfig'  => __( 'Root Web.config', 'wp-simple-firewall' ),
			   ][ $key ] ?? 'BUG: No Title Provided Yet';
	}
}