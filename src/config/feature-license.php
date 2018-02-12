{
  "slug": "license",
  "properties": {
    "slug": "license",
    "name": "Shield Pro",
    "tagline": "The Best In WordPress Security, Only Better.",
    "auto_enabled": true,
    "show_module_menu_item": true,
    "highlight_menu_item": true,
    "hide_summary": false,
    "storage_key": "license",
    "show_central": true,
    "premium": false,
    "access_restricted": true
  },
  "sections": [
    {
      "slug": "section_license_options",
      "title": "License Options",
      "primary": true
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "license_key",
      "section": "section_license_options",
      "default": "",
      "type": "text",
      "link_info": "",
      "link_blog": "",
      "name": "License Key",
      "summary": "License Key",
      "description": "License Key."
    },
    {
      "key": "license_activated_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_deactivated_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_last_checked_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_expires_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_official_status",
      "transferable": false,
      "default": "",
      "section": "section_non_ui"
    },
    {
      "key": "license_deactivated_reason",
      "transferable": false,
      "default": "",
      "section": "section_non_ui"
    },
    {
      "key": "license_registered_email",
      "transferable": false,
      "default": "",
      "section": "section_non_ui"
    },
    {
      "key": "is_license_shield_central",
      "transferable": false,
      "default": false,
      "section": "section_non_ui"
    },
    {
      "key": "last_errors",
      "transferable": false,
      "default": "",
      "section": "section_non_ui"
    },
    {
      "key": "last_error_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    }
  ],
  "definitions": {
    "license_store_url": "https://onedollarplugin.com/edd-sl/",
    "license_item_name": "Shield Security Pro",
    "license_item_id": "6047",
    "license_item_name_sc": "Shield Security Pro (via Shield Central)",
    "license_item_id_sc": "968",
    "license_lack_check_expire_days": 3,
    "license_key_length": 32,
    "license_key_type": "alphanumeric"
  }
}