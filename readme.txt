=== Shield Security: Powerful All-In-One Protection ===
Contributors: paultgoodchild, getshieldsecurity
Donate link: https://shsec.io/bw
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: scan, malware, firewall, two factor authentication, login protection
Requires at least: 3.5.2
Requires PHP: 7.0
Recommended PHP: 7.4
Tested up to: 5.6
Stable tag: 10.1.6

The highest rated WordPress Security plugin, delivering unparalleled, all-in-one protection for you and your customers.

== Description ==

#### Get the highest rated 5* Security Plugin for WordPress

Don't *settle* for the same security plugin just because everyone else does.

#### Shield makes Security for WordPress easy

There's no reason for security to be so complicated.

Shield is the easiest security plugin to setup - you simply activate it and as you learn more, you can tweak the settings to suit your needs best.


#### Non-stop Notifications Are Not Okay.
Wouldn't it be great if your Security plugin took responsibility and handled problems for you without non-stop email notifications?

Shield does exactly this. It's your Silent Guardian.

#### Shield Features You'll Absolutely Love

* [Automatic Bot & IP Blocking](https://shsec.io/j0) - points-based system (that you control) to detect bad bots and block them.
* Block Bot Attacks On Important Forms:
    * Login
    * Registration
    * Password Reset
    * [ShieldPRO] WooCommerce & Easy Digital Downloads
    * [ShieldPRO] Memberpress, LearnPress, BuddyPress, WP Members, ProfileBuilder
* [Limit Login Attempts + Login Cooldown System](https://shsec.io/iw)
* Powerful Firewall Rules
* Restricted Security Admin Access
   * [Prevents Unauthorized Changes To Site Even By Admins](https://shsec.io/ix).
* (MFA) [Two-Factor / Multi-Factor Login Authentication](https://shsec.io/iy):
    * Email
    * Google Authenticator
    * Yubikey
    * [ShieldPRO] U2F Keys
    * [ShieldPRO] Backup Login Codes
    * [ShieldPRO] Multiple Yubikey per User
    * [ShieldPRO] Remember Me (reduces 2FA requests for users)
* [Block XML-RPC](https://shsec.io/iz) (*including* Pingbacks and Trackbacks)
* Block Anonymous Rest API
* Block, Bypass and Analyse IP Addresses
    * [Automatic IP Address Blocking Using Points-Based/Offenses System](https://shsec.io/j0)
    * Block or Bypass individual IPs
    * Block or Bypass IP Subnets
    * Full IP Analysis in 1 place to see their activity on your sites
* Complete WordPress Scanning for Intrusions and Hacks
    * Detect File Changes - [Scan & Repair WordPress Core Files](https://shsec.io/j1)
    * [Detect Unknown/Suspicious PHP Files](https://shsec.io/j2)
    * Detect Abandoned Plugins.
    * [ShieldPRO] Malware Scanner - detects known and unknown malware.
    * [ShieldPRO] Plugin and Theme file scanning - identify file changes in your plugins/themes.
    * [ShieldPRO] Detect Plugins/Themes With Known Vulnerabilities.
* [Create a **Custom Login URL** by hiding wp-login.php](https://shsec.io/j3)
* Detect (and optionally Block) [Comment SPAM from Bots and Humans](https://shsec.io/jf).
* reCAPTCHA & [hCAPTCHA](https://shsec.io/j4) support
* **Never Block Google**: Automatic Detection and Bypass for GoogleBot, Bing and other Official Search Engines including:
    * Google
    * Bing,
    * DuckDuckGo
    * Yahoo!
    * Baidu
    * Apple
    * Yandex
* Automatically Detect 3rd Party Services and Prevent Blocking Of:
    * ManageWP / iControlWP / MainWP
    * Pingdom, NodePing, Statuscake, UptimeRobot, GTMetrix
    * Stripe, PayPal IPN
    * CloudFlare, SEMRush
* Full Audit Trail - [Monitor **All** Site Activity, including](https://shsec.io/j5):
    * All login/registration attempts
    * Plugin and Theme installation, activation, deactivation etc.
    * User creation and promotion
    * Page/Post create, update, delete
* Advanced User Sessions Control
    * Restrict Multiple User Login
    * Restrict Users Session To IP
    * Block Use Of Pwned Passwords
    * Block User Enumeration (?author=x)
    * [ShieldPRO] User Suspend - manual and automatic.
* Full/Automatic Support for All IP Address Sources including Proxy Support
* [Full Traffic Log and Request Monitoring](https://shsec.io/j7)
* [HTTP Security Headers & Content Security Policies (CSP)](https://shsec.io/j6)

#### [Full Shield Security Features List](https://shsec.io/shieldfeatures)

### Dedicated Premium Support When You Go PRO

The Shield Security team prioritises email technical support over the WordPress.org forums.
Individual, dedicated technical support is only available to customers who have [purchased Shield Pro](https://shsec.io/ab).

Discover all the perks turning your security Pro at [our Shield Security store](https://shsec.io/ab).

## Our Mission

We're on a mission to liberate people who manage websites from unnecessarily repetitive work by automating as much as possible for you.

We have three rules that apply to everything we do, and you'll see these when you use our products or contact us for help:

1.  Make everything as simple and easy-to-use as possible (and no simpler!).
1.  Be reliable – we make sure our products do what they promise.
1.  Take ownership for resolving problems - we will solve the problem if we can, or point you towards the solution.

This all combines to make it much more difficult for spambots (and also human spammers as they have to now wait) to work their dirty magic :)

== Installation ==

Note: When you enable the plugin, the firewall is not automatically turned on. This plugin contains various different sections of
protection for your site and you should choose which you need based on your own requirements.

Why do we do this? It's simple: performance and optimization - there is no reason to automatically turn on features for people that don't
need it as each site and set of requirements is different.

This plugin should install as any other WordPress.org repository plugin.

1.	Browse to Plugins -> Add Plugin
1.	Search: Shield
1.	Click Install
1.	Click to Activate.

A new menu item will appear on the left-hand side called 'Shield'.

== Frequently Asked Questions ==

Please see the dedicated [help centre](https://shsec.io/firewallhelp) for details on features and some FAQs.

= How does the Shield compare with other WordPress Security Plugins? =

Easy - we're just better! ;)

Firstly, we don't modify a single core WordPress or web hosting file. This is important and explains why randomly you upgrade your security plugin and your site dies.

Ideally you shouldn't use this along side other Anti-SPAM plugins or security plugins. If there is a feature you need, please feel free to suggest it in the support forums.

= My server has a firewall, why do I need this plugin? =

This plugin is an application layer firewall, not a server/network firewall.  It is designed to interpret web calls to your site to
look for attempts to circumvent it and gain unauthorized access.

Your network firewall is designed to restrict access to your server based on certain types of network traffic.  The Shield
is designed to restrict access to your site, based on certain type of web calls.

= How does the IP Whitelist work? =

Any IP address that is on the whitelist will not be subject to **any of the firewall processing**.  This setting takes priority over all other settings.

= Does the IP Whitelist support IP ranges? =

Yes. To specify a range you use CIDR notation.  E.g. ABC.DEF.GHJ.KMP/16

= I want to review and manage IP addresses, where can I do that? =

You can use IP Lists section. This is an essential tool you can use to analyse IP address, review information concerning blocked and bypassed IP addresses.

It shows you geo-location information and all the request made to your site by that IP, including offenses and any logged-in users.

= I've locked myself out from my own site! =

This happens when any the following 3 conditions are met:

*	you have added your IP address to the firewall blacklist,
*	you have enabled 2 factor authentication and email doesn't work on your site (and you haven't chosen the override option)

You can completely turn OFF (and ON) the Shield by creating a special file in the plugin folder.

Here's how:

1.	Open up an FTP connection to your site, browse to the plugin folder <your site WordPress root>/wp-content/plugins/wp-simple-firewall/
1.	Create a new file in here called: "forceOff".
1.	Load any page on your WordPress site.
1.	After this, you'll find your Shield has been switched off.

If you want to turn the firewall on in the same way, create a file called "forceOn".

Remember: If you leave one of these files on the server, it will override your on/off settings, so you should delete it when you no longer need it.

= Which takes precedence... whitelist or blacklist? =

Whitelist. So if you have the same address in both lists, it'll be whitelisted and allowed to pass before the blacklist comes into effect.

= Can I assist with development? =

Yes! We actively [develop our plugin on Github](https://github.com/FernleafSystems/wp-simple-firewall) and the best thing you can do is submit pull request and bug reports which we'll review.

= How does the pages/parameters whitelist work? =

It is a comma-separated list of pages and parameters. A NEW LINE should be taken for each new page name and its associated parameters.

The first entry on each line (before the first comma) is the page name. The rest of the items on the line are the parameters.

The following are some simple examples to illustrate:

**edit.php, featured**

On the edit.php page, the parameter with the name 'featured' will be ignored.

**admin.php, url, param01, password**

Any parameters that are passed to the page ending in 'admin.php' with the names 'url', 'param01' and 'password' will
be excluded from the firewall processing.

*, url, param, password

Putting a star first means that these exclusions apply to all pages.  So for every page that is accessed, all the parameters
that are url, param and password will be ignored by the firewall.

= How does the login cooldown work? =

Login Cooldown prevents more than 1 login attempt to your site every "so-many" seconds.  So if you enable a login cooldown
of 60 seconds, only 1 login attempt will be processed every 60 seconds.  If you login incorrectly, you wont be able to attempt another
login for a further 60 seconds.

This system completely blocks any level of brute-force login attacks and a cooldown of just 1 second goes a long way.

More Info: https://shsec.io/2t

= How does the GASP Login Guard work? =

This is best [described on the blog](https://shsec.io/2u)

= How does the 2-factor authentication work? =

2-Factor Authentication [is best described here](https://shsec.io/2v).

= I'm not receiving the email with 2FA verification code.? =

Email delivery is a huge problem with WordPress sites and is very common.

Your WordPress is not designed to send emails. The best solution is to use a service that is dedicated to the purpose of sending emails.

[This is what we recommend](https://shsec.io/jj).

= I'm getting an update message although I have auto update enabled? =

The Automatic (Background) WordPress updates happens on a WordPress schedule - it doesn't happen immediately when an update is detected.
You can either manually upgrade, or WordPress will handle it in due course.

= I'm getting large volumes of comment SPAM. How can I stop this? =

You can block 100% of automated spam bots and also block and analyse human-generated spam. [This is best described here](https://shsec.io/jg).

= Do you offer White Label? =

Yes, we do. You can essentially rename the Shield plugin to whatever you would like it to be.

It ensures a more consistent brand offering and presents your business offering as a more holistic, integrated solution.

We go into [further detail here](https://shsec.io/jh).

= I’d like to customise 2FA emails sent to my site users. How can I do that? =

You can use our custom [templates for this purpose](https://shsec.io/ji).

= How can I remove the WordPress admin footer message that displays my IP address? =

You can add some custom code to your functions.php exactly as the following:

`add_filter( 'icwp_wpsf_print_admin_ip_footer', '__return_false' );`

= How can I change the text/html in the Plugin Badge? =

Use the following filter and return the HTML/Text you wish to display:

`add_filter( 'icwp_shield_plugin_badge_text', 'your_function_to_return_text' );`

= How can I change the roles for login notification emails? =

Use the following filter and return the role in the function:

`add_filter( 'icwp-wpsf-login-notification-email-role', 'your_function_to_return_role' );`

Possible options are: network_admin, administrator, editor, author, contributor, subscriber

= What changes go into each Shield version? =

The changelog outlines the main changes for each release. We group changes by minor release "Series". Changes in smaller "point" releases are highlighted
 using **(.1)** notation.  So for example, version 10.1**.1** will have changelog items appended with **(.1)**

You can view the entire [Shield changelog here](https://shsec.io/shieldwporgfullchangelog).

== Screenshots ==

1. A top-level dashboard that shows all the important things you need to know at-a-glance.
2. IP Whitelist and Blacklists lets you manage access and blocks on your site with ease.
3. A full audit log lets you see everything that happens on your site and why, and by whom.
4. Track user sessions and monitor who is logged-into your site and what they're doing.
5. Simple, clean options pages that let you configure Shield Security and all its options easily.

== Changelog ==

The full Shield Changelog can be viewed from our home page:

#### [Full Shield Security Changelog](https://shsec.io/shieldwporgfullchangelog)

ShieldPRO delivers exclusive security features to the serious site administrator to maximise site security
You'll also have direct access to our technical support team.

[Go Pro](https://shsec.io/aa) or grab the [free ShieldPRO Trial](https://shsec.io/shieldfreetrialwporgreadme).