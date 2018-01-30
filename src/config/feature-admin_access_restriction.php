{
  "slug": "admin_access_restriction",
  "properties": {
    "slug": "admin_access_restriction",
    "name": "Security Admin",
    "show_module_menu_item": false,
    "storage_key": "admin_access_restriction",
    "tagline": "Protect your Security Plugin, not just your WordPress site",
    "menu_title": "Security Admin",
    "show_central": true,
    "access_restricted": true,
    "premium": false,
    "order": 20
  },
  "admin_notices": {
    "certain-options-restricted": {
      "id": "certain-options-restricted",
      "schedule": "conditions",
      "valid_admin": true,
      "type": "warning"
    },
    "admin-users-restricted": {
      "id": "admin-users-restricted",
      "schedule": "conditions",
      "valid_admin": true,
      "type": "warning"
    }
  },
  "sections": [
    {
      "slug": "section_admin_access_restriction_settings",
      "primary": true,
      "title": "Security Admin Restriction Settings",
      "title_short": "Security Admin Settings",
      "summary": [
        "Purpose - Restrict access using a simple Access Key.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug": "section_admin_access_restriction_areas",
      "title": "Security Admin Restriction Zones",
      "title_short": "Access Restriction Zones",
      "summary": [
        "Purpose - Restricts access to key WordPress areas for all users not authenticated with the Security Admin Access system.",
        "Recommendation - Use of this feature is highly recommend."
      ]
    },
    {
      "slug": "section_enable_plugin_feature_admin_access_restriction",
      "title": "Enable Plugin Feature: WordPress Security Admin",
      "title_short": "Disable Module",
      "summary": [
        "Purpose - Restricts access to this plugin preventing unauthorized changes to your security settings.",
        "Recommendation - Keep the Security Admin feature turned on.",
        "You need to also enter a new Access Key to enable this feature."
      ]
    },
    {
      "slug": "section_whitelabel",
      "title": "Shield White Label",
      "title_short": "White Label",
      "summary": [
        "Purpose - Rename and re-brand the Shield Security plugin for your client site installations.",
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_admin_access_restriction",
      "section": "section_enable_plugin_feature_admin_access_restriction",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/40",
      "link_blog": "http://icwp.io/wpsf02",
      "name": "Enable Security Admin",
      "summary": "Enforce Security Admin Access Restriction",
      "description": "Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin."
    },
    {
      "key": "admin_access_key",
      "section": "section_admin_access_restriction_settings",
      "sensitive": true,
      "default": "",
      "type": "password",
      "link_info": "http://icwp.io/42",
      "link_blog": "",
      "name": "Security Admin Access Key",
      "summary": "Provide/Update Security Admin Access Key",
      "description": "Careful: If you forget this, you could potentially lock yourself out from using this plugin."

    },
    {
      "key": "admin_access_timeout",
      "section": "section_admin_access_restriction_settings",
      "default": 30,
      "type": "integer",
      "link_info": "http://icwp.io/41",
      "link_blog": "",
      "name": "Security Admin Timeout",
      "summary": "Specify An Automatic Timeout Interval For Security Admin Access",
      "description": "This will automatically expire your Security Admin Session. Does not apply until you enter the access key again. Default: 60 minutes."
    },
    {
      "key": "admin_access_restrict_options",
      "section": "section_admin_access_restriction_areas",
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/a0",
      "link_blog": "http://icwp.io/wpsf32",
      "name": "Pages",
      "summary": "Restrict Access To Key WordPress Posts And Pages Actions",
      "description": "Careful: This will restrict access to page/post creation, editing and deletion. Note: Selecting 'Edit' will also restrict all other options."
    },
    {
      "key": "admin_access_restrict_admin_users",
      "section": "section_admin_access_restriction_areas",
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/a0",
      "link_blog": "",
      "name": "Admin Users",
      "summary": "Restrict Access To Create/Delete/Modify Other Admin Users",
      "description": "Careful: This will restrict the ability of WordPress administrators from creating, modifying or promoting other administrators."
    },
    {
      "key": "admin_access_restrict_plugins",
      "section": "section_admin_access_restriction_areas",
      "type": "multiple_select",
      "default": null,
      "value_options": [
        {
          "value_key": "activate_plugins",
          "text": "Activate"
        },
        {
          "value_key": "install_plugins",
          "text": "Install"
        },
        {
          "value_key": "update_plugins",
          "text": "Update"
        },
        {
          "value_key": "delete_plugins",
          "text": "Delete"
        }
      ],
      "link_info": "http://icwp.io/a0",
      "link_blog": "http://icwp.io/wpsf21",
      "summary": "Restrict Access To Key WordPress Plugin Actions",
      "description": "Careful: This will restrict access to plugin installation, update, activation and deletion. Note: Selecting 'Activate' will also restrict all other options."

    },
    {
      "key": "admin_access_restrict_themes",
      "section": "section_admin_access_restriction_areas",
      "type": "multiple_select",
      "default": null,
      "value_options": [
        {
          "value_key": "switch_themes",
          "text": "Activate"
        },
        {
          "value_key": "edit_theme_options",
          "text": "Edit Theme Options"
        },
        {
          "value_key": "install_themes",
          "text": "Install"
        },
        {
          "value_key": "update_themes",
          "text": "Update"
        },
        {
          "value_key": "delete_themes",
          "text": "Delete"
        }
      ],
      "link_info": "http://icwp.io/a0",
      "link_blog": "http://icwp.io/wpsf21",
      "summary": "Restrict Access To WordPress Theme Actions",
      "description": "Careful: This will restrict access to theme installation, update, activation and deletion."
    },
    {
      "key": "admin_access_restrict_posts",
      "section": "section_admin_access_restriction_areas",
      "type": "multiple_select",
      "default": null,
      "value_options": [
        {
          "value_key": "edit",
          "text": "Create / Edit"
        },
        {
          "value_key": "publish",
          "text": "Publish"
        },
        {
          "value_key": "delete",
          "text": "Delete"
        }
      ],
      "link_info": "http://icwp.io/a0",
      "link_blog": "http://icwp.io/wpsf21",
      "summary": "Restrict Access To Key WordPress Posts And Pages Actions",
      "description": "Careful: This will restrict access to page/post creation, editing and deletion."
    },
    {
      "key": "whitelabel_enable",
      "section": "section_whitelabel",
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Enable White Label",
      "summary": "Activate Your White Label Settings",
      "description": "Use this option to turn on/off the whole White Label feature."
    },
    {
      "key": "whitelabel_name",
      "section": "section_whitelabel",
      "default": "Shield Security",
      "type": "text",
      "link_info": "",
      "link_blog": "",
      "name": "Plugin name",
      "summary": "The Name Of Your Plugin",
      "description": "The Name Of Your Plugin."
    },
    {
      "key": "whitelabel_tagline",
      "section": "section_whitelabel",
      "default": "Secure Your Sites With The World's Most Powerful WordPress Security Plugin",
      "type": "text",
      "link_info": "",
      "link_blog": "",
      "name": "Plugin Tag Line",
      "summary": "The Tag Line Of The Plugin",
      "description": "The Tag Line Of The Plugin."
    },
    {
      "key": "whitelabel_home_url",
      "section": "section_whitelabel",
      "default": "http://icwp.io/home",
      "type": "text",
      "link_info": "",
      "link_blog": "",
      "name": "Home URL",
      "summary": "Plugin Home Page URL",
      "description": "When a user clicks the home link for this plugin, this is where they'll be directed."
    },
    {
      "key": "whitelabel_iconurl",
      "section": "section_whitelabel",
      "default": "",
      "type": "text",
      "link_info": "",
      "link_blog": "",
      "name": "Icon URL",
      "summary": "Plugin Icon URL",
      "description": "The URL of the icon displayed in the menu and in the admin pages."
    }
  ],
  "definitions": {
    "help_video_id": "214855538",
    "admin_access_options_to_restrict": {
      "wpms_options": [
        "admin_email",
        "site_name",
        "registration"
      ],
      "wpms_pages": [
        "settings.php"
      ],
      "wp_options": [
        "blogname",
        "blogdescription",
        "siteurl",
        "home",
        "admin_email",
        "users_can_register",
        "comments_notify",
        "comment_moderation",
        "blog_public"
      ],
      "wp_pages": [
        "options-general.php",
        "options-discussion.php",
        "options-reading.php",
        "options.php"
      ]
    }
  }
}