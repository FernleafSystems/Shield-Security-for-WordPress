<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack;

class Constants {

	/**
	 * @var Zone\BaseZone[]
	 */
	public const ZONES = [
		Zone\SnapPlugins::class,
		Zone\SnapThemes::class,
		Zone\SnapAdmins::class,
		Zone\SnapComments::class,
		Zone\SnapPosts::class,
		Zone\SnapPages::class,
		Zone\SnapDB::class,
		Zone\SnapMedia::class,
	];
	public const DIFF_TYPE_ADDED = 'added';
	public const DIFF_TYPE_CHANGED = 'changed';
	public const DIFF_TYPE_REMOVED = 'removed';
	public const DIFF_TYPES = [
		self::DIFF_TYPE_ADDED,
		self::DIFF_TYPE_REMOVED,
		self::DIFF_TYPE_CHANGED,
	];
}