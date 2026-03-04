<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;

class InvestigateOverviewRowsBuilder {

	public function forUser( \WP_User $subject, array $summaryStats, array $context = [] ) :array {
		$recentIps = $context[ 'recent_ips' ] ?? [];
		$recentIpsText = \is_array( $recentIps ) && !empty( $recentIps )
			? \implode( ', ', \array_map( '\trim', $recentIps ) )
			: __( 'None Recorded', 'wp-simple-firewall' );

		$role = (string)( $context[ 'role' ] ?? __( 'Unknown', 'wp-simple-firewall' ) );
		$lastLoginIp = (string)( $context[ 'last_login_ip' ] ?? __( 'Unknown', 'wp-simple-firewall' ) );
		$eventScore = (string)( $context[ 'event_score' ] ?? 0 );
		$shieldStatus = (string)( $context[ 'shield_status' ] ?? __( 'Tracked', 'wp-simple-firewall' ) );

		return [
			[
				'label' => __( 'Username', 'wp-simple-firewall' ),
				'value' => $subject->user_login,
			],
			[
				'label' => __( 'Display Name', 'wp-simple-firewall' ),
				'value' => \trim( $subject->display_name ),
			],
			[
				'label' => __( 'Email', 'wp-simple-firewall' ),
				'value' => $subject->user_email,
			],
			[
				'label' => __( 'Role', 'wp-simple-firewall' ),
				'value' => $role,
			],
			[
				'label' => __( 'Last Login IP', 'wp-simple-firewall' ),
				'value' => $lastLoginIp,
			],
			[
				'label' => __( 'Recent IPs', 'wp-simple-firewall' ),
				'value' => $recentIpsText,
			],
			[
				'label' => __( 'Event Score', 'wp-simple-firewall' ),
				'value' => $eventScore,
			],
			[
				'label' => __( 'Shield Status', 'wp-simple-firewall' ),
				'value' => $shieldStatus,
			],
		];
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
				'label' => __( 'Name', 'wp-simple-firewall' ),
				'value' => $info[ 'name' ],
			],
			[
				'label' => __( 'Slug', 'wp-simple-firewall' ),
				'value' => $info[ 'slug' ],
			],
			[
				'label' => __( 'Version', 'wp-simple-firewall' ),
				'value' => $info[ 'version' ],
			],
			[
				'label'      => __( 'Author', 'wp-simple-firewall' ),
				'value'      => $author,
				'value_href' => $authorUrl,
			],
			[
				'label' => $assetIdentifierLabel,
				'value' => $info[ 'file' ],
			],
			[
				'label' => __( 'Install Directory', 'wp-simple-firewall' ),
				'value' => $info[ 'dir' ],
			],
			[
				'label' => __( 'Installed', 'wp-simple-firewall' ),
				'value' => $info[ 'installed_at' ],
			],
			[
				'label' => __( 'Active Status', 'wp-simple-firewall' ),
				'value' => !empty( $flags[ 'is_active' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			],
		];

		if ( $subjectType === InvestigationTableContract::SUBJECT_TYPE_PLUGIN ) {
			$rows[] = [
				'label' => __( 'Update Available Status', 'wp-simple-firewall' ),
				'value' => !empty( $flags[ 'has_update' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			];
			$rows[] = [
				'label' => __( 'Vulnerability Status', 'wp-simple-firewall' ),
				'value' => ( $vulnerabilities[ 'count' ] > 0 )
					? __( 'Known Vulnerabilities', 'wp-simple-firewall' )
					: __( 'No Known Vulnerabilities', 'wp-simple-firewall' ),
			];
		}
		elseif ( $subjectType === InvestigationTableContract::SUBJECT_TYPE_THEME ) {
			$rows[] = [
				'label' => __( 'Child Theme Status', 'wp-simple-firewall' ),
				'value' => !empty( $flags[ 'is_child' ] )
					? __( 'Yes', 'wp-simple-firewall' )
					: __( 'No', 'wp-simple-firewall' ),
			];
		}

		return $rows;
	}

	public function forCore( string $coreVersion, bool $hasCoreUpdate, string $installDirectory ) :array {
		return [
			[
				'label' => __( 'WordPress Version', 'wp-simple-firewall' ),
				'value' => $coreVersion,
			],
			[
				'label' => __( 'Core Update Status', 'wp-simple-firewall' ),
				'value' => $hasCoreUpdate
					? __( 'An update is available.', 'wp-simple-firewall' )
					: __( 'No update available.', 'wp-simple-firewall' ),
			],
			[
				'label' => __( 'Install Directory', 'wp-simple-firewall' ),
				'value' => $installDirectory,
			],
		];
	}
}
