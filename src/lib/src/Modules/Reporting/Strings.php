<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[]
	 */
	protected function getAdditionalDisplayStrings() {
		return [
			'title_license_summary'    => __( 'License Summary', 'wp-simple-firewall' ),
			'title_license_activation' => __( 'License Activation', 'wp-simple-firewall' ),
			'check_availability'       => __( 'Check License Availability For This Site', 'wp-simple-firewall' ),
			'check_license'            => __( 'Check License', 'wp-simple-firewall' ),
			'clear_license'            => __( 'Clear License Status', 'wp-simple-firewall' ),
			'url_to_activate'          => __( 'URL To Activate', 'wp-simple-firewall' ),
			'activate_site_in'         => sprintf(
				__( 'Activate this site URL in your %s control panel', 'wp-simple-firewall' ),
				__( 'Keyless Activation', 'wp-simple-firewall' )
			),
			'license_check_limit'      => sprintf( __( 'Licenses may be checked once every %s seconds', 'wp-simple-firewall' ), 20 ),
			'more_frequent'            => __( 'more frequent checks will be ignored', 'wp-simple-firewall' ),
			'incase_debug'             => __( 'In case of activation problems, click the link', 'wp-simple-firewall' ),

			'product_name'    => __( 'Name', 'wp-simple-firewall' ),
			'license_active'  => __( 'Active', 'wp-simple-firewall' ),
			'license_status'  => __( 'Status', 'wp-simple-firewall' ),
			'license_key'     => __( 'Key', 'wp-simple-firewall' ),
			'license_expires' => __( 'Expires', 'wp-simple-firewall' ),
			'license_email'   => __( 'Owner', 'wp-simple-firewall' ),
			'last_checked'    => __( 'Checked', 'wp-simple-firewall' ),
			'last_errors'     => __( 'Error', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'lic_check_success'   => [
				__( 'Pro License check succeeded.', 'wp-simple-firewall' )
			],
			'lic_fail_email'      => [
				__( 'License check failed. Sending Warning Email.', 'wp-simple-firewall' )
			],
			'lic_fail_deactivate' => [
				__( 'License check failed. Deactivating Pro.', 'wp-simple-firewall' )
			],
		];
	}
}