<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;

class InvestigateOverviewRowsBuilder {

	public function forUser( \WP_User $subject, array $summaryStats ) :array {
		$sessions = $summaryStats[ 'sessions' ][ 'count' ];
		$activity = $summaryStats[ 'activity' ][ 'count' ];
		$requests = $summaryStats[ 'requests' ][ 'count' ];
		$ips = $summaryStats[ 'ips' ][ 'count' ];

		return [
			[
				'label' => __( 'User ID', 'wp-simple-firewall' ),
				'value' => (string)$subject->ID,
			],
			[
				'label' => __( 'Login', 'wp-simple-firewall' ),
				'value' => $subject->user_login,
			],
			[
				'label' => __( 'Email', 'wp-simple-firewall' ),
				'value' => $subject->user_email,
			],
			[
				'label' => __( 'Display Name', 'wp-simple-firewall' ),
				'value' => \trim( $subject->display_name ),
			],
			[
				'label' => __( 'Sessions Count', 'wp-simple-firewall' ),
				'value' => (string)$sessions,
			],
			[
				'label' => __( 'Activity Count', 'wp-simple-firewall' ),
				'value' => (string)$activity,
			],
			[
				'label' => __( 'Requests Count', 'wp-simple-firewall' ),
				'value' => (string)$requests,
			],
			[
				'label' => __( 'IP Addresses Count', 'wp-simple-firewall' ),
				'value' => (string)$ips,
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
