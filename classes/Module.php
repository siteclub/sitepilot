<?php

namespace Sitepilot;

use Sitepilot\Model;

class Module
{
    /**
     * The unique module id.
     *
     * @var string
     */
    static protected $module = 'undefined';

    /**
     * The module name.
     *
     * @var string
     */
    static protected $name = 'Undefined';

    /**
     * The module description.
     *
     * @var string
     */
    static protected $description;

    /**
     * The module menu priority.
     *
     * @var string
     */
    static protected $priority = 50;

    /**
     * Require other modules.
     *
     * @var string
     */
    static protected $require = [];

    /**
     * @return void
     */
    static public function init()
    {
        if (is_admin() && isset($_REQUEST['page']) && in_array($_REQUEST['page'], array('sitepilot-settings', 'sitepilot-multisite-settings'))) {
            /* Filters */
            add_filter('sp_admin_settings_nav_items', get_called_class() . '::filter_admin_settings_nav_items');

            /* Actions */
            add_action('sp_admin_settings_render_forms', get_called_class() . '::action_admin_settings_render_forms');
            add_action('sp_admin_settings_save', get_called_class() . '::action_save_settings');
        }
    }

    /**
     * Runs before module initialization.
     *
     * @return void
     */
    static public function before_init()
    {
        if (count(get_called_class()::$require) > 0) {
            foreach (get_called_class()::$require as $module) {
                add_filter('sp_modules_enabled_setting_' . $module, '__return_true');
            }

            add_filter('sp_modules_enabled_settings', get_called_class() . '::filter_enabled_modules', 10, 1);
        }
    }

    /**
     * Enable required modules.
     *
     * @param array $modules
     * @return void
     */
    static public function filter_enabled_modules($modules)
    {
        foreach (get_called_class()::$require as $module) {
            if (!in_array($module, $modules)) {
                $modules[] = $module;
            }
        }
        return $modules;
    }

    /**
     * Allow module to disable itself by overriding the is_active method.
     *
     * @return boolean
     */
    static public function is_active()
    {
        return true;
    }

    /**
     * Returns the module ID.
     *
     * @return string $id
     */
    static public function get_id()
    {
        return get_called_class()::$module;
    }

    /**
     * Returns module setting fields.
     *
     * @return array
     */
    static public function get_fields()
    {
        $fields = [];

        if (method_exists(get_called_class(), 'fields')) {
            $fields = get_called_class()::fields();
        }

        return apply_filters('sp_' . static::$module . '_fields', $fields);
    }

    /**
     * Returns an array of all settings that are enabled.
     *
     * @return array
     */
    static public function get_enabled_settings()
    {
        $settings = Model::get_admin_settings_option('_sp_' . static::$module . '_enabled_settings', true, []);

        if (false !== $key = array_search('all', $settings)) {
            unset($settings[$key]);
        }

        return apply_filters('sp_' . static::$module . '_enabled_settings', $settings);
    }

    /**
     * Checks to see if a module setting is enabled.
     *
     * @param string $setting
     * @param boolean $enable_all return true when all options are enabled
     * @return boolean
     */
    static public function is_setting_enabled($setting)
    {
        return apply_filters('sp_' . static::$module . '_enabled_setting_' . $setting, in_array($setting, self::get_enabled_settings()));
    }

    /**
     * Returns an array of all module settings.
     *
     * @return array
     */
    static public function get_settings()
    {
        $settings = Model::get_admin_settings_option('_sp_' . static::$module . '_settings', true, []);

        return apply_filters('sp_' . static::$module . '_settings', $settings);
    }

    /**
     * Returns a setting value.
     * 
     * @param string $setting
     * @param mixed $default
     * @return mixed
     */
    static public function get_setting($setting, $default = '')
    {
        $settings = self::get_settings();
        $return = '';

        if (array_key_exists($setting, $settings) && !empty($settings[$setting])) {
            $return = $settings[$setting];
        } else {
            if (!empty($default)) {
                $return = $default;
            } else {
                $fields = self::get_fields();
                if (array_key_exists($setting, $fields) && isset($fields[$setting]['default'])) {
                    $return = $fields[$setting]['default'];
                }
            }
        }

        return apply_filters('sp_' . static::$module . '_setting_' . $setting, $return);
    }

    /**
     * Returns the total number of checkboxes.
     *
     * @return int
     */
    static public function get_checkbox_count()
    {
        $count = 0;

        foreach (get_called_class()::get_fields() as $setting) {
            if ($setting['type'] == 'checkbox' && (!isset($setting['active']) || $setting['active'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Adds module nav items to the admin settings.
     *
     * @param array $nav_items
     * @return array
     */
    static public function filter_admin_settings_nav_items($nav_items)
    {
        if (count(get_called_class()::get_fields()) > 0) {
            $nav_items[static::$module] = array(
                'title' => static::$name,
                'show' => is_network_admin() || !Model::is_multisite(),
                'priority' => static::$priority
            );
        }

        return $nav_items;
    }

    /**
     * Renders the admin settings module forms.
     *
     * @return void
     */
    static public function action_admin_settings_render_forms()
    {
        $class = get_called_class();
        include SITEPILOT_DIR . 'includes/admin-settings-module.php';
    }

    /** 
     * Saves the module settings.
     *
     * @return void
     */
    static public function action_save_settings()
    {
        if (isset($_POST['sp-' . static::$module . '-nonce']) && wp_verify_nonce($_POST['sp-' . static::$module . '-nonce'], static::$module)) {
            $enabled = array();

            if (isset($_POST['sp-' . static::$module . '-enabled']) && is_array($_POST['sp-' . static::$module . '-enabled'])) {
                $enabled = array_map('sanitize_text_field', $_POST['sp-' . static::$module . '-enabled']);
            }

            Model::update_admin_settings_option('_sp_' . static::$module . '_enabled_settings', $enabled, true);

            $settings = array();

            if (isset($_POST['sp-' . static::$module]) && is_array($_POST['sp-' . static::$module])) {
                $settings = array_map('stripslashes_deep', $_POST['sp-' . static::$module]);
                $settings = array_map('wp_kses_post', $settings);
            }

            Model::update_admin_settings_option('_sp_' . static::$module . '_settings', $settings, true);

            get_called_class()::saved();
            do_action('sp_module_' . static::$module . '_saved');

            // Redirect after module settings are saved (because some functions need to load early)
            wp_redirect(get_admin_url(null, 'options-general.php?page=sitepilot-settings'));
        }
    }

    /**
     * Called when the module settings are saved.
     *
     * @return void
     */
    static public function saved()
    {
        // Nothing here
    }

    /**
     * Called when the modules are saved.
     * 
     * @return void
     */
    static public function modules_saved()
    {
        // Nothing here 
    }
}
