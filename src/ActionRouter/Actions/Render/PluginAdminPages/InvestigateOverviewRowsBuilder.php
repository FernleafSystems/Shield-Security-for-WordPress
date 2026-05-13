<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;

class InvestigateOverviewRowsBuilder {

	public function forUser( \WP_User $subject, array $context = [] ) :array {
		$recentIps = $context[ 'recent_ips' ] ?? [];
		$recentIpsText = \is_array( $recentIps ) && !empty( $recentIps )
			? \implode( ', ', \array_map( '\trim', $recentIps ) )
			: __( 'None Recorded', 'wp-simple-firewall' );

		$role = (string)( $context[ 'role' ] ?? __( 'Unknown', 'wp-simple-firewall' ) );
		$lastLoginIp = (string)( $context[ 'last_login_ip' ] ?? __( 'Unknown', 'wp-simple-firewall' ) );
		$shieldStatus = (string)( $context[ 'shield_status' ] ?? __( 'Active', 'wp-simple-firewall' ) );
		$wpProfileHref = (string)( $context[ 'wp_profile_href' ] ?? '' );

		$rows = [
			[
				'key'   => 'username',
				'label' => __( 'Username', 'wp-simple-firewall' ),
				'value' => $subject->user_login,
			],
			[
				'key'   => 'display_name',
				'label' => __( 'Display Name', 'wp-simple-firewall' ),
				'value' => \trim( $subject->display_name ),
			],
			[
				'key'   => 'email',
				'label' => __( 'Email', 'wp-simple-firewall' ),
				'value' => $subject->user_email,
			],
			[
				'key'   => 'role',
				'label' => __( 'Role', 'wp-simple-firewall' ),
				'value' => $role,
			],
			[
				'key'   => 'last_login_ip',
				'label' => __( 'Last Login IP', 'wp-simple-firewall' ),
				'value' => $lastLoginIp,
			],
			[
				'key'   => 'recent_ips',
				'label' => __( 'Recent IPs', 'wp-simple-firewall' ),
				'value' => $recentIpsText,
			],
			[
				'key'   => 'shield_status',
				'label' => __( 'Shield Status', 'wp-simple-firewall' ),
				'value' => $shieldStatus,
			],
		];

		if ( $wpProfileHref !== '' ) {
			$rows[] = [
				'key'        => 'wp_profile',
				'label'      => __( 'WordPress Profile', 'wp-simple-firewall' ),
				'value'      => __( 'Open Profile', 'wp-simple-firewall' ),
				'value_href' => $wpProfileHref,
			];
		}

		return $rows;
	}

	public function forAsset(
		array $assetData,
		array $vulnerabilities,
		string $subjectType,
		string $assetIdentifierLabel
	) :array {
		$info = $assetData[ 'info' ];
		$flags = $assetData[ 'flags' ];
		$author = $info[ 'author' ];
		$authorUrl = $info[ 'author_url' ];

		$rows = [
			[
				'key'   => 'name',
				'label' => __( 'Name', 'wp-simple-firewall' ),
				'value' => $info[ 'name' ],
			],
			[
				'key'   => 'slug',
				'label' => __( 'Slug', 'wp-simple-firewall' ),
				'value' => $info[ 'slug' ],
			],
			[
				'key'   => 'version',
				'label' => __( 'Version', 'wp-simple-firewall' ),
				'value' => $info[ 'version' ],
			],
			[
				'key'        => 'author',
				'label'      => __( 'Author', 'wp-simple-firewall' ),
				'value'      => $author,
				'value_href' => $authorUrl,
			],
			[
				'key'   => 'asset_identifier',
				'label' => $assetIdentifierLabel,
				'value' => $info[ 'file' ],
			],
			[
				'key'   => 'install_directory',
				'label' => __( 'Install Directory', 'wp-simple-firewall' ),
				'value' => $info[ 'dir' ],
			],
			[
				'key'   => 'installed_at',
				'label' => __( 'Installed', 'wp-simple-firewall' ),
				'value' => $info[ 'installed_at' ],
			],
			[
				'key'       => 'active_status',
				'label'     => __( 'Active Status', 'wp-simple-firewall' ),
				'value_key' => !empty( $flags[ 'is_active' ] ) ? 'active' : 'inactive',
				'value'     => !empty( $flags[ 'is_active' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			],
		];

		if ( $subjectType === InvestigationTableContract::SUBJECT_TYPE_PLUGIN ) {
			$rows[] = [
				'key'       => 'update_available_status',
				'label'     => __( 'Update Available Status', 'wp-simple-firewall' ),
				'value_key' => !empty( $flags[ 'has_update' ] ) ? 'update_available' : 'no_update_available',
				'value'     => !empty( $flags[ 'has_update' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			];
			$rows[] = [
				'key'       => 'vulnerability_status',
				'label'     => __( 'Vulnerability Status', 'wp-simple-firewall' ),
				'value_key' => ( $vulnerabilities[ 'count' ] > 0 ) ? 'known_vulnerabilities' : 'no_known_vulnerabilities',
				'value'     => ( $vulnerabilities[ 'count' ] > 0 )
					? __( 'Known Vulnerabilities', 'wp-simple-firewall' )
					: __( 'No Known Vulnerabilities', 'wp-simple-firewall' ),
			];
		}
		elseif ( $subjectType === InvestigationTableContract::SUBJECT_TYPE_THEME ) {
			$rows[] = [
				'key'       => 'child_theme_status',
				'label'     => __( 'Child Theme Status', 'wp-simple-firewall' ),
				'value_key' => !empty( $flags[ 'is_child' ] ) ? 'child_theme' : 'not_child_theme',
				'value'     => !empty( $flags[ 'is_child' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			];
		}

		return $rows;
	}

	public function forCore( string $coreVersion, bool $hasCoreUpdate, string $installDirectory ) :array {
		return [
			[
				'key'   => 'wordpress_version',
				'label' => __( 'WordPress Version', 'wp-simple-firewall' ),
				'value' => $coreVersion,
			],
			[
				'key'       => 'core_update_status',
				'label'     => __( 'Core Update Status', 'wp-simple-firewall' ),
				'value_key' => $hasCoreUpdate ? 'update_available' : 'no_update_available',
				'value'     => $hasCoreUpdate
					? __( 'An update is available.', 'wp-simple-firewall' )
					: __( 'No update available.', 'wp-simple-firewall' ),
			],
			[
				'key'   => 'install_directory',
				'label' => __( 'Install Directory', 'wp-simple-firewall' ),
				'value' => $installDirectory,
			],
		];
	}
}
