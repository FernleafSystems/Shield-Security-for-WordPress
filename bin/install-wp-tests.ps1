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

Function Test-WordPressCoreValid {
    $wpLoad = Join-Path $WP_CORE_DIR "wp-load.php"
    $httpBootstrap = Join-Path $WP_CORE_DIR "wp-includes\class-wp-http.php"
    $requestsAutoload = Join-Path $WP_CORE_DIR "wp-includes\Requests\src\Autoload.php"
    $legacyRequests = Join-Path $WP_CORE_DIR "wp-includes\class-requests.php"

    if (-Not (Test-Path $wpLoad) -or -Not (Test-Path $httpBootstrap)) {
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

# Create directories if they don't exist or refresh stale installs
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
    Download-File -Url "https://wordpress.org/${ArchiveName}.tar.gz" -Output "$TMPDIR/wordpress.tar.gz"
    tar -zxmf "$TMPDIR/wordpress.tar.gz" -C $WP_CORE_DIR --strip-components 1
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
