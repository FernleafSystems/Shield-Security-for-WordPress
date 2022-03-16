<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

class Collate {

	/**
	 * @return array
	 */
	public function run() :array {
		return [
			'users'    => ( new BuildUsers() )->run(),
			'post'     => ( new BuildPosts() )->run(),
			'pages'    => ( new BuildPages() )->run(),
			'plugins'  => ( new BuildPlugins() )->run(),
			'themes'   => ( new BuildThemes() )->run(),
			'comments' => ( new BuildComments() )->run(),
			'media'    => ( new BuildMedia() )->run(),
		];
	}
}
