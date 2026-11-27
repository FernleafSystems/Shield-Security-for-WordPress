# PowerShell script for setting up WordPress test environment

Param(
    [string]$DB_NAME = "wordpress_test",
    [string]$DB_USER = "root",
    [string]$DB_PASS = "",
    [string]$DB_HOST = "localhost",
    [string]$WP_VERSION = "latest"
)

$ErrorActionPreference = "Stop"

# Paths
$TMPDIR = [System.IO.Path]::GetTempPath().TrimEnd('\\')
$WP_TESTS_DIR = "$TMPDIR\wordpress-tests-lib"
$WP_CORE_DIR = "$TMPDIR\wordpress"
# The archive the install was unpacked from. It's kept, as it says what the install should hold.
$WP_CORE_ARCHIVE = "$TMPDIR\wordpress.tar.gz"
# Windows' own tar. Started from Git Bash, plain "tar" is Git's, which reads "C:\..." as a remote host and fails.
$WindowsTar = Join-Path $env:SystemRoot "System32\tar.exe"
$TAR = If (Test-Path $WindowsTar) { $WindowsTar } Else { "tar" }

Function Get-WordPressVersion {
    param ([string]$VersionFile)
    if ($VersionFile -match "\`$wp_version\s*=\s*'([^']+)'") {
        return $Matches[1]
    }
    return ''
}

# The install is kept between runs, where anything that clears out unused files can take some of it away. It can
# only be checked against the archive it came from, so it has to be that archive's version of WordPress.
Function Test-WordPressCoreMatchesArchive {
    $versionFile = Join-Path $WP_CORE_DIR "wp-includes\version.php"
    if (-Not (Test-Path $WP_CORE_ARCHIVE) -or -Not (Test-Path $versionFile)) {
        return $false
    }

    $installed = Get-WordPressVersion (Get-Content $versionFile -Raw)
    $archived = Get-WordPressVersion ((& $TAR -xzOf $WP_CORE_ARCHIVE "wordpress/wp-includes/version.php") -join "`n")
    return ($installed -ne '') -and ($installed -eq $archived)
}

# Puts back every file of the archive that the install no longer has. Files that are there are left as they are.
Function Restore-MissingWordPressCoreFiles {
    $missing = @(& $TAR -tzf $WP_CORE_ARCHIVE | Where-Object {
        $_ -notmatch '/$' -and -Not (Test-Path -LiteralPath (Join-Path $WP_CORE_DIR ($_ -replace '^wordpress/', '')))
    })
    if ($missing.Count -eq 0) {
        return
    }

    $list = "$TMPDIR\wordpress-missing-files.txt"
    [System.IO.File]::WriteAllText($list, ($missing -join "`n") + "`n")
    & $TAR -zxmf $WP_CORE_ARCHIVE -C $WP_CORE_DIR --strip-components 1 -T $list
    $restored = ($LASTEXITCODE -eq 0)
    Remove-Item -LiteralPath $list -Force
    if (-Not $restored) {
        throw "Could not restore $($missing.Count) missing WordPress files from $WP_CORE_ARCHIVE"
    }
    Write-Host "Restored $($missing.Count) missing WordPress files from the cached archive."
}

Function Test-WordPressCoreValid {
    $wpLoad = Join-Path $WP_CORE_DIR "wp-load.php"
    $httpBootstrap = Join-Path $WP_CORE_DIR "wp-includes\class-wp-http.php"
    $diffRenderer = Join-Path $WP_CORE_DIR "wp-includes\Text\Diff\Renderer.php"
    $inlineDiffRenderer = Join-Path $WP_CORE_DIR "wp-includes\Text\Diff\Renderer\inline.php"
    $requestsAutoload = Join-Path $WP_CORE_DIR "wp-includes\Requests\src\Autoload.php"
    $legacyRequests = Join-Path $WP_CORE_DIR "wp-includes\class-requests.php"
    # No test loads the front controller, so it's among the first files to go from an install that's being
    # cleared out. With no archive to check against, its absence is what says the install is damaged.
    $frontController = Join-Path $WP_CORE_DIR "index.php"

    if (
        -Not (Test-Path $wpLoad) -or
        -Not (Test-Path $httpBootstrap) -or
        -Not (Test-Path $diffRenderer) -or
        -Not (Test-Path $inlineDiffRenderer) -or
        -Not (Test-Path $frontController)
    ) {
        return $false
    }

    $httpBootstrapContent = Get-Content $httpBootstrap -Raw
    if ($httpBootstrapContent -match "Requests/src/Autoload\.php") {
        return Test-Path $requestsAutoload
    }
    if ($httpBootstrapContent -match "class-requests\.php") {
        return Test-Path $legacyRequests
    }

    return $true
}

Function Test-WordPressTestsLibValid {
    return (Test-Path (Join-Path $WP_TESTS_DIR "includes\functions.php")) -and (Test-Path (Join-Path $WP_TESTS_DIR "includes\bootstrap.php"))
}

# Function to download files
Function Download-File {
    param (
        [string]$Url,
        [string]$Output
    )
    Invoke-WebRequest -Uri $Url -OutFile $Output
}

# Determine WordPress SVN branch/tag
If ($WP_VERSION -match "^\d+\.\d+-(beta|RC)\d+") {
    $WP_BRANCH = $WP_VERSION -replace '-.*'
    $WP_TESTS_TAG = "branches/$WP_BRANCH"
} ElseIf ($WP_VERSION -match "^\d+\.\d+$") {
    $WP_TESTS_TAG = "branches/$WP_VERSION"
} ElseIf ($WP_VERSION -match "\d+\.\d+\.\d+") {
    If ($WP_VERSION -match "\d+\.\d+\.0") {
        $WP_TESTS_TAG = "tags/${WP_VERSION.Substring(0,$WP_VERSION.Length-2)}"
    } Else {
        $WP_TESTS_TAG = "tags/$WP_VERSION"
    }
} ElseIf ($WP_VERSION -eq 'nightly' -or $WP_VERSION -eq 'trunk') {
    $WP_TESTS_TAG = 'trunk'
} Else {
    $LatestVersion = (Invoke-RestMethod -Uri "http://api.wordpress.org/core/version-check/1.7/").offers | Select-Object -First 1 | Select-Object -Expand version
    If (-Not $LatestVersion) { throw "Latest WordPress version could not be found" }
    $WP_TESTS_TAG = "tags/$LatestVersion"
}

# Create directories if they don't exist or refresh stale installs. An install that can be checked against its
# archive first has its missing files put back.
If ((Test-Path $WP_CORE_DIR) -and (Test-WordPressCoreMatchesArchive)) {
    Restore-MissingWordPressCoreFiles
}

If ((Test-Path $WP_CORE_DIR) -and -Not (Test-WordPressCoreValid)) {
    Remove-Item -LiteralPath $WP_CORE_DIR -Recurse -Force
}

If (-Not (Test-Path $WP_CORE_DIR)) {
    New-Item -ItemType Directory -Force -Path $WP_CORE_DIR | Out-Null
    
    # Download and extract WordPress
    If ($WP_VERSION -eq 'latest') {
        $ArchiveName = 'latest'
    } Else {
        $ArchiveName = "wordpress-$WP_VERSION"
    }
    Download-File -Url "https://wordpress.org/${ArchiveName}.tar.gz" -Output $WP_CORE_ARCHIVE
    & $TAR -zxmf $WP_CORE_ARCHIVE -C $WP_CORE_DIR --strip-components 1
    If ($LASTEXITCODE -ne 0) {
        throw "Could not unpack $WP_CORE_ARCHIVE into $WP_CORE_DIR"
    }
}

# Setup the test suite
If ((Test-Path $WP_TESTS_DIR) -and -Not (Test-WordPressTestsLibValid)) {
    Remove-Item -LiteralPath $WP_TESTS_DIR -Recurse -Force
}

If (-Not (Test-Path $WP_TESTS_DIR)) {
    New-Item -ItemType Directory -Force -Path $WP_TESTS_DIR | Out-Null
    svn co "https://develop.svn.wordpress.org/$WP_TESTS_TAG/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
    svn co "https://develop.svn.wordpress.org/$WP_TESTS_TAG/tests/phpunit/data/" "$WP_TESTS_DIR/data"
}

# Configure wp-tests-config.php
$configFile = "$WP_TESTS_DIR/wp-tests-config.php"
$configSampleFile = "$WP_TESTS_DIR/wp-tests-config-sample.php"
If (-Not (Test-Path $configFile)) {
    Download-File -Url "https://develop.svn.wordpress.org/$WP_TESTS_TAG/wp-tests-config-sample.php" -Output $configSampleFile
    Copy-Item $configSampleFile $configFile
    (Get-Content $configFile) -replace "youremptytestdbnamehere", $DB_NAME | 
        ForEach-Object {$_ -replace "yourusernamehere", $DB_USER } | 
        ForEach-Object {$_ -replace "yourpasswordhere", $DB_PASS } |
        ForEach-Object {$_ -replace "localhost", $DB_HOST} |
        Set-Content $configFile
}

Write-Host "WordPress test environment setup complete!"
Write-Host "WP_TESTS_DIR: $WP_TESTS_DIR"
Write-Host "WP_CORE_DIR: $WP_CORE_DIR"
Write-Host "You can now run tests with: PHPUNIT_DIR\phpunit"
