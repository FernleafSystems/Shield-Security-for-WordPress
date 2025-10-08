<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;

class CommonDisplayStrings {

	public static function pick( array $keys = [] ) :array {
		$all = self::all();
		if ( empty( $keys ) ) {
			return $all;
		}
		return \array_intersect_key( $all, \array_flip( $keys ) );
	}

	public static function get( string $key, string $default = '' ) :string {
		return self::all()[ $key ] ?? $default;
	}

	public static function all() :array {
		return [
			'access_restricted_label' => __( 'Access Restricted', 'wp-simple-firewall' ),
			'alert_label'         => __( 'Alert', 'wp-simple-firewall' ),
			'debug_label'         => __( 'Debug', 'wp-simple-firewall' ),
			'details_label'       => __( 'Details', 'wp-simple-firewall' ),
			'generated_label'     => __( 'Generated', 'wp-simple-firewall' ),
			'help_label'          => __( 'Help', 'wp-simple-firewall' ),
			'important_label'     => __( 'Important', 'wp-simple-firewall' ),
			'info_label'          => __( 'Info', 'wp-simple-firewall' ),
			'name_label'          => __( 'Name', 'wp-simple-firewall' ),
			'author_label'        => __( 'Author', 'wp-simple-firewall' ),
			'ip_address'          => __( 'IP Address', 'wp-simple-firewall' ),
			'ip_address_label'    => __( 'IP Address', 'wp-simple-firewall' ),
			'more_info_label'     => __( 'More Info', 'wp-simple-firewall' ),
			'notice_label'        => __( 'Notice', 'wp-simple-firewall' ),
			'patch_label'         => __( 'Patch', 'wp-simple-firewall' ),
			'history_label'       => __( 'History', 'wp-simple-firewall' ),
			'diff_label'          => __( 'Diff', 'wp-simple-firewall' ),
			'contents_label'      => __( 'Contents', 'wp-simple-firewall' ),
			'report_type_label'   => __( 'Report Type', 'wp-simple-firewall' ),
			'scan_results_label'  => __( 'Scan Results', 'wp-simple-firewall' ),
			'site_url_label'      => __( 'Site URL', 'wp-simple-firewall' ),
			'slug_label'          => __( 'Slug', 'wp-simple-firewall' ),
			'time_label'          => __( 'Time', 'wp-simple-firewall' ),
			'options_label'       => __( 'Options', 'wp-simple-firewall' ),
			'never_label'         => __( 'Never', 'wp-simple-firewall' ),
			'version_label'       => __( 'Version', 'wp-simple-firewall' ),
			'upgrade_guide_label' => __( 'Upgrade Guide', 'wp-simple-firewall' ),
			'username'            => __( 'Username', 'wp-simple-firewall' ),
			'username_label'      => __( 'Username', 'wp-simple-firewall' ),
			'user_sessions_label' => __( 'User Sessions', 'wp-simple-firewall' ),
			'view_scan_results_label' => __( 'View Scan Results', 'wp-simple-firewall' ),
			'view_report_label'   => __( 'View Report', 'wp-simple-firewall' ),
			'yes_label'           => __( 'Yes', 'wp-simple-firewall' ),
			'no_label'            => __( 'No', 'wp-simple-firewall' ),
			'user_label'          => __( 'User', 'wp-simple-firewall' ),
			'request_path_label'  => __( 'Request Path', 'wp-simple-firewall' ),
			'timestamp_label'     => __( 'Timestamp', 'wp-simple-firewall' ),
			'warning_label'       => __( 'Warning', 'wp-simple-firewall' ),
			'collapse_label'      => __( 'Collapse', 'wp-simple-firewall' ),
			'url_label'           => __( 'URL', 'wp-simple-firewall' ),
		];
	}
}
