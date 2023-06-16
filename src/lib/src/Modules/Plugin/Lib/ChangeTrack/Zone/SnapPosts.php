<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\ZoneReportPosts;
use FernleafSystems\Wordpress\Services\Services;

class SnapPosts extends BaseZone {

	public const SLUG = 'posts';

	public function getZoneReporterClass() :string {
		return ZoneReportPosts::class;
	}

	/**
	 * @return array[] - key is user ID, values are arrays with keys: id, user_login, user_pass, user_email, is_admin
	 */
	public function snap() :array {
		return $this->retrieve();
	}

	protected function retrieve( array $params = [] ) :array {
		$actual = [];

		$params = Services::DataManipulation()->mergeArraysRecursive( $this->getBaseParameters(), $params );

		do {
			/** @var \WP_Post[] $result */
			$result = get_posts( $params );
			if ( \is_array( $result ) ) {
				foreach ( $result as $post ) {
					$actual[ $post->ID ] = [
						'uniq'         => $post->ID,
						'slug'         => $post->post_name,
						'title'        => $post->post_title,
						'modified_at'  => \strtotime( $post->post_modified_gmt ),
						'hash_content' => \sha1( $post->post_content ),
						'hash_title'   => \sha1( $post->post_title ),
					];
				}
			}

			$params[ 'paged' ]++;
		} while ( !empty( $result ) );

		return $actual;
	}

	protected function getBaseParameters() :array {
		return [
			'numberposts' => 50,
			'post_status' => 'publish',
			'paged'       => 1,
			'post_type'   => 'post',
		];
	}
}