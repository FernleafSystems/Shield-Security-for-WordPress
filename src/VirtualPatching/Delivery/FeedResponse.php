<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

final class FeedResponse {

	public const FILE = 'file';

	public const NONE = 'none';

	public const SCHEMA_RETIRED = 'schema_retired';

	public const UNCHANGED = 'unchanged';

	public const TRANSPORT = 'transport';

	public string $type;

	public ?string $bytes;

	public function __construct( string $type, ?string $bytes = null ) {
		$this->type = $type;
		$this->bytes = $bytes;
	}
}
