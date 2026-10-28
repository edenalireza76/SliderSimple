<?php
/**
 * Plugin Name: Performance Image Carousel for Elementor
 * Description: Lightweight Elementor banner slider/carousel: real IMG/PICTURE output with srcset, mobile art-direction, HTML text layer and 4 designed skins (classic, notch sale, notch luxury, full-width marketplace) — one tiny vanilla-JS file, no jQuery/Swiper.
 * Version: 4.1.0
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Requires Plugins: elementor
 * Text Domain: performance-carousel-elementor
 * Elementor tested up to: 4.3
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('PCE_VERSION')) {
    define('PCE_VERSION', '4.1.0');
    define('PCE_PATH', plugin_dir_path(__FILE__));
    define('PCE_URL', plugin_dir_url(__FILE__));
}

/**
 * Register dependencies for the Elementor assets manager.
 * Registering assets does not enqueue them globally.
 */
function pce_register_assets() {
    if (!wp_style_is('pce-carousel', 'registered')) {
        wp_register_style('pce-carousel', PCE_URL . 'assets/carousel.min.css', [], PCE_VERSION);
    }
    if (!wp_script_is('pce-carousel', 'registered')) {
        wp_register_script('pce-carousel', PCE_URL . 'assets/carousel.min.js', [], PCE_VERSION, true);
        if (function_exists('wp_script_add_data')) {
            wp_script_add_data('pce-carousel', 'strategy', 'defer');
        }
    }
}
add_action('wp_enqueue_scripts', 'pce_register_assets', 5);
add_action('elementor/frontend/after_register_scripts', 'pce_register_assets', 5);
add_action('elementor/frontend/after_register_styles', 'pce_register_assets', 5);

/** New dedicated category: searching by English or Persian widget title works. */
function pce_register_category($elements_manager) {
    $elements_manager->add_category('pce_widgets', [
        'title' => 'اسلایدرهای بهینه | Performance Carousel',
        'icon'  => 'fa fa-images',
    ]);
}
add_action('elementor/elements/categories_registered', 'pce_register_category');

/** Official Elementor widget registration hook (not a WP sidebar widget). */
function pce_register_widget($widgets_manager) {
    if (!class_exists('\\Elementor\\Widget_Base')) {
        return;
    }
    require_once PCE_PATH . 'includes/class-pce-widget.php';
    if (!class_exists('PCE_Image_Carousel_Widget')) {
        return;
    }
    $already = method_exists($widgets_manager, 'get_widget_types') ? $widgets_manager->get_widget_types() : [];
    if (!isset($already['pce_image_carousel'])) {
        $widgets_manager->register(new PCE_Image_Carousel_Widget());
    }
}
add_action('elementor/widgets/register', 'pce_register_widget', 20);

/** Clear actionable notice only when Elementor is not loaded. */
function pce_missing_elementor_notice() {
    if (!current_user_can('manage_options') || did_action('elementor/loaded')) { return; }
    echo '<div class="notice notice-warning"><p><strong>Performance Image Carousel:</strong> افزونه Elementor باید فعال باشد تا ویجت اسلایدر در ویرایشگر دیده شود.</p></div>';
}
add_action('admin_notices', 'pce_missing_elementor_notice');

/** A real diagnostics screen with a discoverable plugin-row link. */
function pce_admin_menu() {
    add_menu_page(
        'وضعیت ویجت اسلایدر', 'اسلایدر بهینه', 'manage_options', 'pce-diagnostics',
        'pce_diagnostics_page', 'dashicons-images-alt2', 81
    );
}
add_action('admin_menu', 'pce_admin_menu');

function pce_plugin_links($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=pce-diagnostics')) . '">وضعیت ثبت ویجت</a>');
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pce_plugin_links');

function pce_diagnostics_page() {
    if (!current_user_can('manage_options')) { return; }
    $elementor_loaded = did_action('elementor/loaded') > 0 && class_exists('\\Elementor\\Plugin');
    $registered = false;
    $registered_names = [];
    $registration_hook_ran = did_action('elementor/widgets/register') > 0;
    if ($elementor_loaded && isset(\Elementor\Plugin::$instance->widgets_manager)) {
        // Forces lazy Elementor widget registration on this admin page.
        $widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
        $registered = isset($widgets['pce_image_carousel']);
        $registered_names = array_keys($widgets);
    }
    $theme = wp_get_theme();
    $data = [
        'نسخه افزونه' => PCE_VERSION,
        'Elementor' => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : 'غیرفعال',
        'Elementor Pro' => defined('ELEMENTOR_PRO_VERSION') ? ELEMENTOR_PRO_VERSION : 'نصب/فعال نیست (الزامی نیست)',
        'قالب فعال' => $theme->get('Name') . ' ' . $theme->get('Version'),
        'PHP' => PHP_VERSION,
        'هوک رسمی ثبت ویجت اجرا شده' => $registration_hook_ran ? 'YES' : 'NO',
        'وضعیت ثبت در Widget Manager' => $registered ? 'REGISTERED ✓' : 'NOT REGISTERED ✗',
        'نام سیستمی ویجت' => 'pce_image_carousel',
        'فایل PHP ویجت' => file_exists(PCE_PATH . 'includes/class-pce-widget.php') ? 'OK' : 'MISSING',
        'فایل CSS فشرده' => file_exists(PCE_PATH . 'assets/carousel.min.css') ? 'OK' : 'MISSING',
        'فایل JS فشرده' => file_exists(PCE_PATH . 'assets/carousel.min.js') ? 'OK' : 'MISSING',
    ];
    echo '<div class="wrap" dir="rtl"><h1>وضعیت ویجت اسلایدر بهینه</h1>';
    echo '<p>این صفحه بخشی از خود افزونه است و همیشه از منوی «اسلایدر بهینه» در پیشخوان در دسترس است.</p>';
    echo '<table class="widefat striped" style="max-width:820px"><tbody>';
    foreach ($data as $name => $value) {
        echo '<tr><th style="width:42%">' . esc_html($name) . '</th><td style="direction:ltr;text-align:left">' . esc_html($value) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<h2>پیدا کردن ویجت در المنتور</h2>';
    echo '<p>در پنل المنتور عنوان <strong>اسلایدر تصویر فوق سبک</strong> یا کلمه <strong>Performance Carousel</strong> را جستجو کنید. ویجت در دسته «اسلایدرهای بهینه | Performance Carousel» قرار دارد.</p>';
    if (!$registered) {
        echo '<p><strong>ثبت انجام نشده:</strong> از فعال بودن Elementor و غیرفعال نبودن ویجت در Elementor → Element Manager مطمئن شوید. اگر همچنان ثبت نشده، همین صفحه را برای بررسی ارسال کنید.</p>';
    } else {
        echo '<p><strong>ویجت ثبت شده:</strong> اگر هنوز در پنل پیدایش نمی‌کنید، در Elementor → Element Manager وضعیت ویجت را فعال کنید؛ سپس فایل‌های Elementor و کش را بازسازی کنید.</p>';
    }
    echo '</div>';
}
