{
  "slug": "admin_access_restriction",
  "properties": {
    "name": "WordPress Security Admin",
    "show_feature_menu_item": true,
    "storage_key": "admin_access_restriction",
    "tagline": "Protect your security plugin not just your WordPress site",
    "menu_title": "Security Admin"
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
      "slug": "section_enable_plugin_feature_admin_access_restriction",
      "primary": true,
      "title": "Enable Plugin Feature: WordPress Security Admin",
      "title_short": "Enable / Disable",
      "summary": [
        "Purpose - Restricts access to this plugin preventing unauthorized changes to your security settings.",
        "Recommendation - Keep the Security Admin feature turned on.",
        "You need to also enter a new Access Key to enable this feature."
      ]
    },
    {
      "slug": "section_admin_access_restriction_settings",
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
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_admin_access_restriction",
      "section": "section_enable_plugin_feature_admin_access_restriction",
      "transferable": true,
      "default": "N",
      "type": "checkbox",
      "link_info": "http://icwp.io/40",
      "link_blog": "http://icwp.io/wpsf02",
      "name": "Enable Security Admin",
      "summary": "Enforce Security Admin Access Restriction",
      "description": "Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin."
    },
    {
      "key": "admin_access_key",
      "section": "section_enable_plugin_feature_admin_access_restriction",
      "transferable": true,
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
      "transferable": true,
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
      "transferable": true,
      "default": "Y",
      "type": "checkbox",
      "link_info": "http://icwp.io/wpsf32",
      "link_blog": "",
      "name": "Pages",
      "summary": "Restrict Access To Key WordPress Posts And Pages Actions",
      "description": "Careful: This will restrict access to page/post creation, editing and deletion. Note: Selecting 'Edit' will also restrict all other options."
    },
    {
      "key": "admin_access_restrict_admin_users",
      "section": "section_admin_access_restriction_areas",
      "transferable": true,
      "default": "N",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Admin Users",
      "summary": "Restrict Access To Create/Delete/Modify Other Admin Users",
      "description": "Careful: This will restrict the ability of WordPress administrators from creating, modifying or promoting other administrators."
    },
    {
      "key": "admin_access_restrict_plugins",
      "section": "section_admin_access_restriction_areas",
      "transferable": true,
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
      "link_info": "http://icwp.io/wpsf21",
      "link_blog": ""
    },
    {
      "key": "admin_access_restrict_themes",
      "section": "section_admin_access_restriction_areas",
      "transferable": true,
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
      "link_info": "http://icwp.io/wpsf21",
      "link_blog": ""
    },
    {
      "key": "admin_access_restrict_posts",
      "section": "section_admin_access_restriction_areas",
      "transferable": true,
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
      "link_info": "http://icwp.io/wpsf21",
      "link_blog": ""
    },
    {
      "key": "current_plugin_version",
      "section": "section_non_ui"
    }
  ],
  "definitions": {
    "admin_access_key_cookie_name": "icwp_wpsf_aakcook",
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