<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Why the early loader could not be installed, removed or kept. The value is stored with the state; the
 * settings page turns it into a message.
 */
enum InstallFailure :string {

	case Multisite = 'multisite';
	case FilesystemNotDirect = 'filesystem_not_direct';
	case UnsafePath = 'unsafe_path';
	case HostPrependNotAbsolute = 'host_prepend_not_absolute';
	case Conflict = 'conflict';
	case MalformedMarkers = 'malformed_markers';
	case LoopbackUnavailable = 'loopback_unavailable';
	case StubWriteFailed = 'stub_write_failed';
	case StubSmokeFailed = 'stub_smoke_failed';
	case ConfigWriteFailed = 'config_write_failed';
	case SiteFailedReverted = 'site_failed_reverted';
	case InstallInterrupted = 'install_interrupted';
}
