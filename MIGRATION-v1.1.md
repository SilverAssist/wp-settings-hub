# Migration Guide: v1.0.0 to v1.1.0

**Date**: October 8, 2025  
**Breaking Changes**: Yes  
**Effort**: Low

---

## 🔴 Overview

Version 1.1.0 introduces a **breaking change** in the menu structure. The Settings Hub now creates a **top-level menu** instead of appearing under WordPress Settings. This provides better visibility and proper hierarchical organization for Silver Assist plugins.

### What Changed

| Aspect | v1.0.0 (Old) | v1.1.0 (New) |
|--------|-------------|--------------|
| **Menu Location** | Settings > Silver Assist | Top-level "Silver Assist" menu |
| **Menu Type** | `add_options_page()` | `add_menu_page()` + `add_submenu_page()` |
| **Dashboard URL** | `options-general.php?page=silver-assist` | `admin.php?page=silver-assist` |
| **Plugin URLs** | `options-general.php?page={slug}` | `admin.php?page={slug}` |
| **Menu Structure** | Flat (no hierarchy) | Hierarchical (parent-child) |
| **Menu Icon** | None | dashicons-shield |
| **Menu Position** | N/A | 80 (after Comments) |

---

## 🎯 Visual Changes

### Before (v1.0.0)
```
WordPress Admin Menu:
├── Dashboard
├── Posts
├── Media
├── ...
└── Settings
    ├── General
    ├── Writing
    ├── Silver Assist          ← Dashboard (standalone)
    ├── Post Revalidate        ← Plugin (standalone)
    └── Security Essentials    ← Plugin (standalone)
```

### After (v1.1.0)
```
WordPress Admin Menu:
├── Dashboard
├── Posts
├── Media
├── ...
├── Settings
│   ├── General
│   ├── Writing
│   └── ...
├── Silver Assist 🛡️           ← Top-level menu
│   ├── Dashboard              ← Hub dashboard
│   ├── Post Revalidate        ← Plugin (submenu)
│   └── Security Essentials    ← Plugin (submenu)
└── ...
```

---

## ⚙️ What You Need to Update

### 1. Plugin Code Changes

**✅ Good News**: Most plugins require **NO CODE CHANGES**!

The hub automatically handles URL generation. Your plugin registration code remains the same:

```php
// This code works in BOTH v1.0.0 and v1.1.0
$hub = SettingsHub::get_instance();
$hub->register_plugin(
    'my-plugin-slug',
    'My Plugin Name',
    [ $this, 'render_settings' ],
    [
        'description' => 'Plugin description',
        'version'     => '1.0.0',
    ]
);
```

### 2. Hard-coded URLs (If Any)

If your plugin has **hard-coded URLs** to the Settings Hub dashboard or other plugins' pages, you need to update them:

**Before (v1.0.0)**:
```php
// ❌ Old URL structure
$dashboard_url = admin_url( 'options-general.php?page=silver-assist' );
$plugin_url    = admin_url( 'options-general.php?page=my-plugin' );
```

**After (v1.1.0)**:
```php
// ✅ New URL structure
$dashboard_url = admin_url( 'admin.php?page=silver-assist' );
$plugin_url    = admin_url( 'admin.php?page=my-plugin' );
```

**Better Approach** (version-agnostic):
```php
// ✅ Best: Use hub's method to get parent slug
$hub = SettingsHub::get_instance();
$parent_slug = $hub->get_parent_slug(); // Returns 'silver-assist'

// Build URL dynamically
$dashboard_url = menu_page_url( $parent_slug, false );
$plugin_url    = menu_page_url( 'my-plugin', false );
```

### 3. Documentation Updates

Update any documentation that references:
- Screenshots showing old menu location
- URLs to settings pages
- Navigation instructions

---

## 🔧 Step-by-Step Migration

### For Plugin Developers

1. **Update Hub Package**:
   ```bash
   cd /path/to/your-plugin
   composer update silverassist/wp-settings-hub
   ```

2. **Search for Hard-coded URLs**:
   ```bash
   # Search for old URL pattern
   grep -r "options-general.php?page=silver-assist" .
   ```

3. **Replace URLs** (if found):
   - Change `options-general.php?page=` to `admin.php?page=`
   - Or use `menu_page_url()` for dynamic URLs

4. **Test Navigation**:
   - [ ] Dashboard loads correctly
   - [ ] Plugin settings page loads
   - [ ] Links between pages work
   - [ ] Tabs navigation works (if enabled)

5. **Update Plugin Version**:
   ```json
   // composer.json
   {
       "require": {
           "silverassist/wp-settings-hub": "^1.1"
       }
   }
   ```

### For End Users

**No action required!** The menu location change is automatic when the hub is updated.

---

## 📊 Compatibility Matrix

| Hub Version | Plugin Code | Hard-coded URLs | Works? |
|-------------|-------------|-----------------|--------|
| v1.0.0      | Old         | Old             | ✅ Yes |
| v1.1.0      | Old         | Old             | ⚠️ Partially (menus work, URLs break) |
| v1.1.0      | Old         | New             | ✅ Yes |
| v1.1.0      | Old         | None            | ✅ Yes (recommended) |

---

## 🐛 Troubleshooting

### Problem: "Silver Assist menu appears twice"

**Cause**: Both old hub (v1.0.0) and new hub (v1.1.0) are loaded.

**Solution**:
```bash
# Remove old package
rm -rf vendor/silverassist/wp-settings-hub

# Clear Composer cache
composer clear-cache

# Install new version
composer install
```

### Problem: "Plugin page shows 404"

**Cause**: Hard-coded URL still uses `options-general.php`.

**Solution**: Update URL to use `admin.php?page=` instead.

### Problem: "Dashboard is blank"

**Cause**: Capability issue or conflicting plugin.

**Solution**:
1. Verify user has `manage_options` capability
2. Disable other plugins temporarily
3. Check WordPress debug log

### Problem: "Menu appears in wrong position"

**Cause**: Another plugin is using position 80.

**Solution**: Hub will still work, WordPress resolves conflicts automatically.

---

## ✅ Testing Checklist

After migration, verify:

- [ ] "Silver Assist" appears as top-level menu with shield icon
- [ ] Dashboard opens when clicking "Silver Assist"
- [ ] Dashboard shows all registered plugins
- [ ] Each plugin appears as submenu under "Silver Assist"
- [ ] Clicking plugin submenu opens correct settings page
- [ ] "Configure" buttons on dashboard work
- [ ] Tabs navigation works (if enabled)
- [ ] No PHP errors in debug log
- [ ] No JavaScript console errors
- [ ] Permissions work correctly (only admin can access)

---

## 📝 Benefits of This Change

### For Users
- ✅ **More visible**: Top-level menu is easier to find
- ✅ **Better organized**: Clear hierarchy with submenus
- ✅ **Professional look**: Custom icon and consistent placement
- ✅ **Easier navigation**: All plugins in one dropdown

### For Developers
- ✅ **Cleaner URLs**: `admin.php` instead of `options-general.php`
- ✅ **True hierarchy**: WordPress properly handles parent-child relationship
- ✅ **Scalable**: Can add unlimited submenus without cluttering Settings
- ✅ **Extensible**: Can add more top-level features in future

---

## 🚀 Rollback Instructions

If you need to temporarily rollback to v1.0.0:

```bash
cd /path/to/your-plugin
composer require silverassist/wp-settings-hub:^1.0
```

**Note**: This is not recommended long-term. Version 1.1.0 is the way forward.

---

## 📞 Support

If you encounter issues during migration:

1. **Check logs**: `wp-content/debug.log`
2. **Enable debugging**:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```
3. **Review this guide**: Make sure all steps were followed
4. **Contact support**: support@silverassist.com

---

## 🎓 Learn More

- [Settings Hub README](README.md) - Complete API documentation
- [Implementation Guide](IMPLEMENTATION.md) - Integration tutorial
- [Integration Examples](integration-guide.php) - Working code samples
- [CHANGELOG](CHANGELOG.md) - Detailed version history

---

**Migration Difficulty**: 🟢 Easy  
**Estimated Time**: 5-15 minutes  
**Breaking Changes**: Yes (URLs only)  
**Recommended Action**: Update immediately

---

**Last Updated**: October 8, 2025  
**Hub Version**: 1.1.0
