<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class DockerHarnessLabels {

	public const HARNESS = 'com.fernleaf.harness';
	public const RUN_ID = 'com.fernleaf.run-id';
	public const LANE = 'com.fernleaf.lane';
	public const LIFECYCLE = 'com.fernleaf.lifecycle';
	public const EXPIRES_AT = 'com.fernleaf.expires-at';

	public const LIFECYCLE_TRANSIENT = 'transient';
	public const LIFECYCLE_REUSABLE = 'reusable';
}
