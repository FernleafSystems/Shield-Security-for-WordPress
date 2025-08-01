
# Shield Security Plugin for WordPress

[![Build Status](https://travis-ci.org/FernleafSystems/Shield-Security-for-WordPress.svg?branch=develop)](https://travis-ci.org/FernleafSystems/Shield)


**Don't Leave Your Site At Risk**
> If your site is vulnerable to attack, you're putting your business and your reputation at serious risk. Getting hacked can mean you're locked out of your site, client data stolen, your website defaced or offline, and Google *will* penalise you.
>
**Why take the risk?**
>
> Download and install Shield now for FREE so that you have the most powerful WordPress security system working for you and protecting your site.
>
**Shield + iControlWP**
> If you have multiple sites, then Shield [combined with iControlWP](https://clk.shldscrty.com/shld8), takes the pain out of managing your websites, and covers your security, daily backup (and restore), and updating plugins/themes

## Development

### Testing

**Docker (Recommended)**: `.\bin\run-tests.ps1 all -Docker` - Zero setup required, runs in isolated containers

**Native**: `.\bin\run-tests.ps1 all` or `composer test` - Uses your local PHP/MySQL setup

**Package Testing**: `.\bin\run-tests.ps1 all -Docker -Package` - Tests built production package

See [TESTING.md](TESTING.md) for complete testing documentation.

### Quick Start Testing

```powershell
# Docker testing (recommended - no setup required)
.\bin\run-tests.ps1 all -Docker

# Native testing (requires local PHP/MySQL)
.\bin\run-tests.ps1 all

# Package testing (tests production build)
.\bin\run-tests.ps1 all -Docker -Package
```

See readme.txt for full details and changelog