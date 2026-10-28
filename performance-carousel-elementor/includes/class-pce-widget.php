<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Performance Image Carousel widget.
 *
 * Control keys from v3.x are kept unchanged so saved widgets keep working after the update.
 * Every visual setting is a CSS custom property; skins only change the defaults of those properties.
 */
class PCE_Image_Carousel_Widget extends \Elementor\Widget_Base {

    const SKINS = array('classic', 'notch', 'notch_wide', 'digikala');
    const EASE = array(
        'smooth' => 'cubic-bezier(.25,.8,.25,1)',
        'ease'   => 'ease',
        'out'    => 'cubic-bezier(.16,1,.3,1)',
        'linear' => 'linear',
    );
    /** Per-skin defaults for choices that are not plain CSS values. */
    const SKIN_DEFAULTS = array(
        'classic'    => array('pos' => 'middle-start', 'icon' => 'end', 'arrows' => 'always', 'notch' => false, 'glyph' => 'chevrons'),
        'notch'      => array('pos' => 'middle-end',   'icon' => 'end', 'arrows' => 'hover',  'notch' => true,  'glyph' => 'arrow'),
        'notch_wide' => array('pos' => 'middle-start', 'icon' => 'end', 'arrows' => 'hover',  'notch' => true,  'glyph' => 'arrow'),
        'digikala'   => array('pos' => 'middle-start', 'icon' => 'none', 'arrows' => 'hover', 'notch' => false, 'glyph' => 'chevrons'),
    );

    public function get_name(): string { return 'pce_image_carousel'; }
    public function get_title(): string { return 'اسلایدر تصویر فوق سبک'; }
    public function get_icon(): string { return 'eicon-slider-push'; }
    public function get_categories(): array { return array('pce_widgets', 'general'); }
    public function get_keywords(): array { return array('carousel','image carousel','media carousel','slider','slides','banner','hero','fade','performance','fast','lightweight','اسلایدر','بنر','تصویر','بهینه','کاروسل','دیجی کالا'); }
    public function get_style_depends(): array { return array('pce-carousel'); }
    public function get_script_depends(): array { return array('pce-carousel'); }
    /** Elementor 3.24+ optimized DOM: no extra .elementor-widget-container wrapper. */
    public function has_widget_inner_wrapper(): bool { return false; }
    /** Output depends only on settings, so Elementor's element cache may store it. */
    protected function is_dynamic_content(): bool { return false; }

    /* ------------------------------------------------------------------ helpers */

    private function sw($key, $label, $default = '', $extra = array()) {
        $this->add_control($key, array_merge(array(
            'label' => $label, 'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => 'بله', 'label_off' => 'خیر', 'return_value' => 'yes', 'default' => $default,
        ), $extra));
    }

    /** Responsive slider bound to a CSS variable on the carousel root. */
    private function vs($key, $label, $var, $min, $max, $units = array('px'), $step = 1, $extra = array(), $tpl = '{{SIZE}}{{UNIT}}') {
        $range = array();
        foreach ($units as $u) {
            $range[$u] = ($u === '%') ? array('min' => 0, 'max' => 100) : (($u === 'em' || $u === 'rem') ? array('min' => 0, 'max' => 10, 'step' => .1) : array('min' => $min, 'max' => $max, 'step' => $step));
        }
        $this->add_responsive_control($key, array_merge(array(
            'label' => $label, 'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => $units, 'range' => $range,
            'selectors' => array('{{WRAPPER}} .oic-carousel' => $var . ':' . $tpl . ';'),
        ), $extra));
    }

    private function vc($key, $label, $var, $extra = array()) {
        $this->add_control($key, array_merge(array(
            'label' => $label, 'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .oic-carousel' => $var . ':{{VALUE}};'),
        ), $extra));
    }

    private function heading($key, $label) {
        $this->add_control($key, array('label' => $label, 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before'));
    }

    private function typo($name, $label, $selector) {
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), array('name' => $name, 'label' => $label, 'selector' => '{{WRAPPER}} ' . $selector));
    }

    private function is_edit() {
        $p = \Elementor\Plugin::$instance;
        return (isset($p->editor) && $p->editor->is_edit_mode()) || (isset($p->preview) && method_exists($p->preview, 'is_preview_mode') && $p->preview->is_preview_mode());
    }

    private function breakpoint($name, $fallback) {
        try {
            $bps = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();
            if (isset($bps[$name])) { return (int) $bps[$name]->get_value(); }
        } catch (\Throwable $e) {}
        return $fallback;
    }

    private function positions() {
        return array(
            'top-start' => 'بالا — شروع', 'top-center' => 'بالا — وسط', 'top-end' => 'بالا — پایان',
            'middle-start' => 'وسط — شروع (راست در فارسی)', 'middle-center' => 'وسط — وسط', 'middle-end' => 'وسط — پایان (چپ در فارسی)',
            'bottom-start' => 'پایین — شروع', 'bottom-center' => 'پایین — وسط', 'bottom-end' => 'پایین — پایان',
        );
    }

    /* ------------------------------------------------------------------ controls */

    protected function register_controls(): void {
        $cm = '\\Elementor\\Controls_Manager';
        $cond_slides = array('source' => 'slides');
        $cond_notch = array('skin' => array('notch', 'notch_wide'));

        /* ---------------- Design ---------------- */
        $this->start_controls_section('oic_skin_section', array('label' => 'استایل اسلایدر'));
        $this->add_control('skin', array('label' => 'استایل', 'type' => $cm::SELECT, 'default' => 'classic', 'options' => array(
            'classic'    => 'کلاسیک (پیش‌فرض)',
            'notch'      => 'بنر حراج — گوشه گرد با بریدگی بالا',
            'notch_wide' => 'بنر لوکس — بریدگی پهن، تیتر با خط کناری',
            'digikala'   => 'تمام‌عرض — مدل دیجی‌کالا',
        ), 'description' => 'هر استایل رنگ، فلش، صفحه‌بندی، دکمه و تایپوگرافی پیش‌فرض خودش را دارد؛ همه از تب استایل قابل تغییرند.'));
        $this->sw('notch', 'بریدگی بالای بنر', 'yes', array('condition' => $cond_notch));
        $this->add_control('notch_color', array('label' => 'رنگ بریدگی (= رنگ پس‌زمینه سکشن)', 'type' => $cm::COLOR, 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-notch-bg:{{VALUE}};'), 'condition' => $cond_notch + array('notch' => 'yes')));
        $this->vs('notch_w', 'عرض بریدگی', '--oic-notch-w', 40, 800, array('px', '%'), 1, array('condition' => $cond_notch + array('notch' => 'yes')));
        $this->vs('notch_h', 'عمق بریدگی', '--oic-notch-h', 6, 160, array('px'), 1, array('condition' => $cond_notch + array('notch' => 'yes')));
        $this->vs('notch_r', 'گردی پایین بریدگی', '--oic-notch-r', 0, 80, array('px'), 1, array('condition' => $cond_notch + array('notch' => 'yes')));
        $this->vs('notch_s', 'گردی شانه‌های بریدگی', '--oic-notch-s', 0, 80, array('px'), 1, array('condition' => $cond_notch + array('notch' => 'yes')));
        $this->end_controls_section();

        /* ---------------- Content: images ---------------- */
        $this->start_controls_section('oic_content', array('label' => 'تصاویر و اسلایدها'));
        $this->add_control('source', array('label' => 'روش افزودن تصاویر', 'type' => $cm::SELECT, 'default' => 'gallery', 'options' => array('slides' => 'اسلایدهای مستقل (متن، دکمه، لینک، تصویر موبایل)', 'gallery' => 'گالری وردپرس (انتخاب چندتایی سریع)')));

        $rep = new \Elementor\Repeater();
        $rep->start_controls_tabs('slide_tabs');
        $rep->start_controls_tab('slide_tab_media', array('label' => 'تصویر'));
        $rep->add_control('image', array('label' => 'تصویر دسکتاپ', 'type' => $cm::MEDIA, 'default' => array('url' => \Elementor\Utils::get_placeholder_image_src()), 'dynamic' => array('active' => true)));
        $rep->add_control('image_mobile', array('label' => 'تصویر موبایل (اختیاری)', 'description' => 'با تگ picture فقط همین فایل در موبایل دانلود می‌شود و نسبت قاب در موبایل از همین تصویر گرفته می‌شود.', 'type' => $cm::MEDIA, 'default' => array('url' => ''), 'dynamic' => array('active' => true)));
        $rep->add_control('alt', array('label' => 'متن ALT', 'type' => $cm::TEXT, 'label_block' => true, 'dynamic' => array('active' => true), 'description' => 'خالی = ALT کتابخانه رسانه'));
        $rep->add_control('title', array('label' => 'عنوان (هاور / کپشن / aria)', 'type' => $cm::TEXT, 'label_block' => true, 'dynamic' => array('active' => true)));
        $rep->add_control('caption', array('label' => 'کپشن زیر تصویر', 'type' => $cm::TEXTAREA, 'rows' => 2, 'dynamic' => array('active' => true)));
        $rep->end_controls_tab();
        $rep->start_controls_tab('slide_tab_text', array('label' => 'متن و دکمه'));
        $rep->add_control('kicker', array('label' => 'متن کوچک بالای تیتر', 'type' => $cm::TEXT, 'label_block' => true, 'dynamic' => array('active' => true), 'description' => 'مثل «تخفیف تا <b>15%</b>» یا «Style auction». تگ b برای درشت کردن عدد.'));
        $rep->add_control('heading', array('label' => 'تیتر', 'type' => $cm::TEXTAREA, 'rows' => 2, 'dynamic' => array('active' => true)));
        $rep->add_control('subtitle', array('label' => 'زیرتیتر', 'type' => $cm::TEXT, 'label_block' => true, 'dynamic' => array('active' => true)));
        $rep->add_control('description', array('label' => 'توضیح', 'type' => $cm::TEXTAREA, 'rows' => 3, 'dynamic' => array('active' => true)));
        $rep->add_control('button_text', array('label' => 'متن دکمه', 'type' => $cm::TEXT, 'dynamic' => array('active' => true)));
        $rep->add_control('link', array('label' => 'لینک', 'type' => $cm::URL, 'placeholder' => 'https://...', 'options' => array('url','is_external','nofollow'), 'dynamic' => array('active' => true)));
        $rep->add_control('content_position', array('label' => 'جایگاه متن این اسلاید', 'type' => $cm::SELECT, 'default' => '', 'options' => array('' => 'پیش‌فرض ویجت') + $this->positions()));
        $rep->end_controls_tab();
        $rep->start_controls_tab('slide_tab_style', array('label' => 'رنگ'));
        $rep->add_control('item_heading_color', array('label' => 'رنگ تیتر', 'type' => $cm::COLOR, 'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}}' => '--oic-h-color:{{VALUE}};')));
        $rep->add_control('item_text_color', array('label' => 'رنگ سایر متن‌ها', 'type' => $cm::COLOR, 'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}}' => '--oic-d-color:{{VALUE}};--oic-k-color:{{VALUE}};--oic-s-color:{{VALUE}};')));
        $rep->add_control('item_btn_bg', array('label' => 'رنگ دکمه', 'type' => $cm::COLOR, 'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}}' => '--oic-btn-bg:{{VALUE}};')));
        $rep->add_control('item_scrim', array('label' => 'رنگ لایه تیره', 'type' => $cm::COLOR, 'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}}' => '--oic-scrim:{{VALUE}};')));
        $rep->add_control('item_bg', array('label' => 'پس‌زمینه قاب', 'type' => $cm::COLOR, 'selectors' => array('{{WRAPPER}} {{CURRENT_ITEM}}' => '--oic-image-bg:{{VALUE}};')));
        $rep->end_controls_tab();
        $rep->end_controls_tabs();

        $this->add_control('slides', array('label' => 'فهرست اسلایدها', 'type' => $cm::REPEATER, 'fields' => $rep->get_controls(), 'title_field' => '{{{ heading || title || "اسلاید" }}}', 'default' => array(
            array('heading' => 'تیتر اسلاید اول', 'description' => 'یک توضیح کوتاه و جذاب برای این بنر بنویسید', 'button_text' => 'خرید'),
            array('heading' => 'تیتر اسلاید دوم', 'description' => 'متن‌ها HTML واقعی هستند؛ برای سئو و خوانایی بهتر', 'button_text' => 'مشاهده'),
            array('heading' => 'تیتر اسلاید سوم', 'button_text' => 'خرید'),
        ), 'condition' => $cond_slides));
        $this->add_control('gallery', array('label' => 'تصاویر گالری', 'type' => $cm::GALLERY, 'default' => array(), 'dynamic' => array('active' => true), 'condition' => array('source' => 'gallery')));
        $this->add_control('gallery_content', array('label' => 'متن روی تصاویر گالری', 'type' => $cm::SELECT, 'default' => 'none', 'options' => array('none' => 'بدون متن', 'title' => 'عنوان رسانه', 'title_caption' => 'عنوان + کپشن رسانه'), 'condition' => array('source' => 'gallery')));
        $this->add_control('image_size', array('label' => 'اندازه تصویر وردپرس', 'type' => $cm::SELECT, 'default' => 'large', 'separator' => 'before', 'options' => array('thumbnail' => 'Thumbnail','medium' => 'Medium','medium_large' => 'Medium Large','large' => 'Large','1536x1536' => '1536','2048x2048' => '2048','full' => 'Full (اصل تصویر)','custom' => 'Custom (ابعاد دلخواه)'), 'description' => 'srcset خودکار است؛ مرورگر کوچک‌ترین فایل مناسب را انتخاب می‌کند.'));
        $this->add_control('custom_image_width', array('label' => 'عرض دلخواه تصویر (px)', 'type' => $cm::NUMBER, 'min' => 100, 'max' => 4000, 'default' => 1200, 'condition' => array('image_size' => 'custom')));
        $this->add_control('custom_image_height', array('label' => 'ارتفاع دلخواه تصویر (px)', 'type' => $cm::NUMBER, 'min' => 100, 'max' => 4000, 'default' => 600, 'condition' => array('image_size' => 'custom')));
        $this->add_control('link_mode', array('label' => 'رفتار لینک تصاویر', 'type' => $cm::SELECT, 'default' => 'custom', 'separator' => 'before', 'options' => array('none' => 'بدون لینک','custom' => 'لینک سفارشی هر اسلاید','file' => 'فایل رسانه + لایت‌باکس')));
        $this->add_control('link_area', array('label' => 'محدوده کلیک لینک', 'type' => $cm::SELECT, 'default' => 'frame', 'options' => array('frame' => 'کل بنر قابل کلیک','button' => 'فقط دکمه'), 'condition' => array('link_mode' => 'custom', 'source' => 'slides')));
        $this->sw('lightbox', 'باز شدن لایت‌باکس (حالت فایل)', 'yes', array('condition' => array('link_mode' => 'file')));
        $this->add_control('caption_mode', array('label' => 'کپشن زیر تصویر', 'type' => $cm::SELECT, 'default' => 'none', 'options' => array('none' => 'بدون کپشن','custom' => 'کپشن سفارشی','title' => 'عنوان رسانه','caption' => 'کپشن رسانه','description' => 'توضیحات رسانه')));
        $this->add_control('overlay', array('label' => 'محتوای هاور', 'type' => $cm::SELECT, 'default' => 'none', 'options' => array('none' => 'بدون پوشش','title' => 'عنوان','caption' => 'کپشن','icon' => 'آیکون ذره‌بین')));
        $this->add_control('overlay_animation', array('label' => 'انیمیشن پوشش', 'type' => $cm::SELECT, 'default' => 'fade', 'options' => array('fade' => 'Fade','up' => 'Slide Up','down' => 'Slide Down','zoom' => 'Zoom'), 'condition' => array('overlay!' => 'none')));
        $this->end_controls_section();

        /* ---------------- Content: text layer ---------------- */
        $this->start_controls_section('oic_text', array('label' => 'لایه متن و دکمه'));
        $this->sw('content_layer', 'نمایش متن روی بنر', 'yes');
        $this->add_control('heading_tag', array('label' => 'تگ تیتر', 'type' => $cm::SELECT, 'default' => 'div', 'options' => array('h1' => 'H1','h2' => 'H2','h3' => 'H3','h4' => 'H4','p' => 'p','div' => 'div (بدون اثر روی ساختار هدینگ)'), 'condition' => array('content_layer' => 'yes')));
        $this->add_control('first_heading_tag', array('label' => 'تگ تیتر اسلاید اول', 'type' => $cm::SELECT, 'default' => '', 'options' => array('' => 'همانند بقیه','h1' => 'H1','h2' => 'H2'), 'condition' => array('content_layer' => 'yes')));
        $this->add_control('content_pos', array('label' => 'جایگاه متن', 'type' => $cm::SELECT, 'default' => '', 'options' => array('' => 'پیش‌فرض استایل') + $this->positions(), 'condition' => array('content_layer' => 'yes')));
        $this->add_responsive_control('content_align', array('label' => 'تراز متن', 'type' => $cm::CHOOSE, 'options' => array(
            'start' => array('title' => 'شروع', 'icon' => 'eicon-text-align-right'), 'center' => array('title' => 'وسط', 'icon' => 'eicon-text-align-center'), 'end' => array('title' => 'پایان', 'icon' => 'eicon-text-align-left'),
        ), 'selectors_dictionary' => array('start' => '--oic-ta2:start;--oic-ai2:flex-start', 'center' => '--oic-ta2:center;--oic-ai2:center', 'end' => '--oic-ta2:end;--oic-ai2:flex-end'),
            'selectors' => array('{{WRAPPER}} .oic-carousel' => '{{VALUE}};'), 'condition' => array('content_layer' => 'yes')));
        $this->add_responsive_control('content_width', array('label' => 'حداکثر عرض متن', 'type' => $cm::SLIDER, 'size_units' => array('%','px'), 'range' => array('%' => array('min' => 10, 'max' => 100), 'px' => array('min' => 100, 'max' => 1400)), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-content-w:{{SIZE}}{{UNIT}};'), 'condition' => array('content_layer' => 'yes')));
        $this->add_responsive_control('content_padding', array('label' => 'فاصله متن از لبه‌ها', 'type' => $cm::DIMENSIONS, 'size_units' => array('px','%','em'), 'selectors' => array('{{WRAPPER}} .oic-content' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'), 'condition' => array('content_layer' => 'yes')));
        $this->add_responsive_control('content_display', array('label' => 'نمایش کل متن', 'type' => $cm::SELECT, 'options' => array('' => 'نمایش','none' => 'مخفی'), 'selectors' => array('{{WRAPPER}} .oic-content' => 'display:{{VALUE}} !important;'), 'condition' => array('content_layer' => 'yes')));
        $this->add_responsive_control('desc_display', array('label' => 'نمایش توضیح و زیرتیتر', 'type' => $cm::SELECT, 'options' => array('' => 'پیش‌فرض (در موبایل مخفی)','block' => 'نمایش','none' => 'مخفی'), 'selectors' => array('{{WRAPPER}} .oic-desc, {{WRAPPER}} .oic-sub' => 'display:{{VALUE}};'), 'condition' => array('content_layer' => 'yes')));
        $this->add_control('content_animation', array('label' => 'انیمیشن ورود متن', 'type' => $cm::SELECT, 'default' => 'up', 'options' => array('up' => 'بالا آمدن','fade' => 'محو','side' => 'از کنار','none' => 'بدون انیمیشن'), 'condition' => array('content_layer' => 'yes')));
        $this->add_control('button_icon', array('label' => 'آیکون دکمه', 'type' => $cm::ICONS, 'skin' => 'inline', 'label_block' => false, 'default' => array('value' => '', 'library' => ''), 'condition' => array('content_layer' => 'yes')));
        $this->add_control('button_icon_mode', array('label' => 'جای آیکون دکمه', 'type' => $cm::SELECT, 'default' => '', 'options' => array('' => 'پیش‌فرض استایل','end' => 'بعد از متن','start' => 'قبل از متن','none' => 'بدون آیکون'), 'condition' => array('content_layer' => 'yes')));
        $this->add_control('scrim', array('label' => 'لایه تیره برای خوانایی', 'type' => $cm::SELECT, 'default' => 'none', 'options' => array('none' => 'ندارد','side' => 'گرادیان از سمت متن','bottom' => 'گرادیان از پایین','full' => 'تمام تصویر'), 'condition' => array('content_layer' => 'yes')));
        $this->end_controls_section();

        /* ---------------- Content: layout ---------------- */
        $this->start_controls_section('oic_layout', array('label' => 'چیدمان و اندازه'));
        $this->add_control('effect', array('label' => 'نوع انتقال', 'type' => $cm::SELECT, 'default' => 'slide', 'options' => array('slide' => 'اسلاید افقی', 'fade' => 'محو شدن (Fade)')));
        $this->add_responsive_control('show', array('label' => 'تعداد اسلاید قابل مشاهده (اعشاری مجاز)', 'type' => $cm::NUMBER, 'min' => 1, 'max' => 10, 'step' => 0.05, 'default' => 1, 'tablet_default' => 1, 'mobile_default' => 1, 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-show:{{VALUE}};'), 'description' => 'مثلاً 1.2 = یک اسلاید کامل + لبه اسلاید بعدی', 'condition' => array('effect' => 'slide')));
        $this->add_responsive_control('scroll', array('label' => 'اسلاید در هر حرکت', 'type' => $cm::NUMBER, 'min' => 1, 'max' => 10, 'step' => 1, 'default' => 1, 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-scroll:{{VALUE}};'), 'condition' => array('effect' => 'slide')));
        $this->add_responsive_control('gap', array('label' => 'فاصله اسلایدها', 'type' => $cm::SLIDER, 'range' => array('px' => array('min' => 0, 'max' => 120)), 'default' => array('size' => 20), 'tablet_default' => array('size' => 14), 'mobile_default' => array('size' => 10), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-gap:{{SIZE}}px;'), 'condition' => array('effect' => 'slide')));
        $this->add_control('height_mode', array('label' => 'روش تعیین ارتفاع', 'type' => $cm::SELECT, 'default' => 'ratio', 'separator' => 'before', 'options' => array('ratio' => 'نسبت تصویر (واکنش‌گرا، بدون CLS)','fixed' => 'ارتفاع ثابت','auto' => 'ارتفاع طبیعی تصویر')));
        $this->add_responsive_control('ratio', array('label' => 'نسبت ابعاد', 'type' => $cm::SELECT, 'default' => '', 'options' => array('' => 'پیش‌فرض استایل','8 / 3' => '8:3 — بنر 1920×720','5 / 2' => '5:2','12 / 5' => '12:5','24 / 5' => '24:5 — نوار عریض (1920×400)','3 / 1' => '3:1','21 / 9' => '21:9','2 / 1' => '2:1','16 / 9' => '16:9','4 / 3' => '4:3','1 / 1' => '1:1 مربع','4 / 5' => '4:5 پرتره','9 / 16' => '9:16','custom' => 'دلخواه'), 'selectors_dictionary' => array('custom' => 'var(--oic-ratio-c,2)'), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-ratio:{{VALUE}};'), 'condition' => array('height_mode' => 'ratio')));
        $this->add_responsive_control('ratio_custom', array('label' => 'نسبت دلخواه (عرض ÷ ارتفاع)', 'type' => $cm::NUMBER, 'min' => .2, 'max' => 8, 'step' => .01, 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-ratio-c:{{VALUE}};'), 'condition' => array('height_mode' => 'ratio', 'ratio' => 'custom')));
        $this->add_responsive_control('height', array('label' => 'ارتفاع تصویر', 'type' => $cm::SLIDER, 'size_units' => array('px','vw','vh'), 'range' => array('px' => array('min' => 40, 'max' => 1200),'vw' => array('min' => 5, 'max' => 120),'vh' => array('min' => 5, 'max' => 100)), 'default' => array('size' => 455, 'unit' => 'px'), 'tablet_default' => array('size' => 320, 'unit' => 'px'), 'mobile_default' => array('size' => 220, 'unit' => 'px'), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-height:{{SIZE}}{{UNIT}};'), 'condition' => array('height_mode' => 'fixed')));
        $this->add_responsive_control('width', array('label' => 'عرض کل ویجت', 'type' => $cm::SLIDER, 'size_units' => array('%','px'), 'range' => array('%' => array('min' => 10, 'max' => 100),'px' => array('min' => 200, 'max' => 2400)), 'default' => array('size' => 100, 'unit' => '%'), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-width:{{SIZE}}{{UNIT}};')));
        $this->add_responsive_control('image_position_x', array('label' => 'نقطه کانونی افقی تصویر (%)', 'type' => $cm::SLIDER, 'range' => array('px' => array('min' => 0, 'max' => 100)), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-position-x:{{SIZE}}%;')));
        $this->add_responsive_control('image_position_y', array('label' => 'نقطه کانونی عمودی تصویر (%)', 'type' => $cm::SLIDER, 'range' => array('px' => array('min' => 0, 'max' => 100)), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-position-y:{{SIZE}}%;')));
        $this->add_responsive_control('fit', array('label' => 'نحوه نمایش تصویر', 'type' => $cm::SELECT, 'options' => array('' => 'پیش‌فرض (Cover)','cover' => 'Cover','contain' => 'Contain','fill' => 'Fill'), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-fit:{{VALUE}};')));
        $this->sw('stretch', 'کشیدگی تصاویر', 'yes');
        $this->add_control('direction', array('label' => 'جهت اسلایدر', 'type' => $cm::SELECT, 'default' => 'ltr', 'options' => array('ltr' => 'اسلاید بعدی سمت راست','rtl' => 'اسلاید بعدی سمت چپ (راست‌به‌چپ)')));
        $this->end_controls_section();

        /* ---------------- Content: behaviour ---------------- */
        $this->start_controls_section('oic_behaviour', array('label' => 'ناوبری، حرکت و عملکرد'));
        $this->add_control('navigation', array('label' => 'ناوبری', 'type' => $cm::SELECT, 'default' => 'both', 'options' => array('both' => 'فلش و صفحه‌بندی','arrows' => 'فقط فلش','dots' => 'فقط صفحه‌بندی','none' => 'هیچ‌کدام')));
        $this->add_control('pagination', array('label' => 'نوع صفحه‌بندی', 'type' => $cm::SELECT, 'default' => 'dots', 'options' => array('dots' => 'نقطه','fraction' => 'شماره اسلاید','progress' => 'نوار پیشرفت'), 'condition' => array('navigation' => array('both','dots'))));
        $this->add_control('prev_icon', array('label' => 'آیکون فلش قبلی', 'type' => $cm::ICONS, 'skin' => 'inline', 'label_block' => false, 'default' => array('value' => '', 'library' => ''), 'recommended' => array('fa-solid' => array('chevron-left','angle-left','arrow-left','circle-chevron-left')), 'condition' => array('navigation' => array('both','arrows'))));
        $this->add_control('next_icon', array('label' => 'آیکون فلش بعدی', 'type' => $cm::ICONS, 'skin' => 'inline', 'label_block' => false, 'default' => array('value' => '', 'library' => ''), 'recommended' => array('fa-solid' => array('chevron-right','angle-right','arrow-right','circle-chevron-right')), 'condition' => array('navigation' => array('both','arrows'))));

        $this->heading('auto_head', 'حرکت خودکار');
        $this->sw('autoplay', 'حرکت خودکار', '');
        $this->add_control('delay', array('label' => 'مکث روی هر اسلاید (ms)', 'type' => $cm::NUMBER, 'default' => 5000, 'min' => 1000, 'max' => 60000, 'step' => 100, 'condition' => array('autoplay' => 'yes')));
        $this->sw('autoplay_progress', 'نمایش زمان‌سنج روی نقطه فعال', '', array('condition' => array('autoplay' => 'yes')));
        $this->sw('play_button', 'دکمه توقف/پخش (دسترس‌پذیری)', '', array('condition' => array('autoplay' => 'yes')));
        $this->sw('pause_hover', 'توقف با هاور ماوس', 'yes');
        $this->sw('pause_interaction', 'توقف دائمی بعد از تعامل کاربر', 'yes', array('condition' => array('autoplay' => 'yes')));

        $this->heading('motion_head', 'انیمیشن و تعامل');
        $this->sw('loop', 'حلقه بی‌نهایت', 'yes');
        $this->add_control('speed', array('label' => 'زمان انتقال (ms)', 'type' => $cm::NUMBER, 'min' => 0, 'max' => 3000, 'step' => 10, 'default' => 450));
        $this->add_control('easing', array('label' => 'منحنی حرکت', 'type' => $cm::SELECT, 'default' => 'smooth', 'options' => array('smooth' => 'نرم (پیش‌فرض)','ease' => 'Ease','out' => 'شروع سریع، توقف نرم','linear' => 'خطی')));
        $this->sw('touch', 'کشیدن با لمس و ماوس', 'yes');
        $this->sw('wheel', 'حرکت با اسکرول افقی تاچ‌پد', '');

        $this->heading('perf_head', 'پرفورمنس');
        $this->sw('lazy', 'Lazy Loading اسلایدهای خارج از دید', 'yes');
        $this->sw('priority_first', 'اولویت بالای تصویر نخست (LCP)', 'yes');
        $this->add_control('sizes_max', array('label' => 'حداکثر عرض نمایش در دسکتاپ (px)', 'type' => $cm::NUMBER, 'min' => 0, 'max' => 3840, 'description' => 'عرض کانتینر سایت (مثلاً 1270). مرورگر فایل کوچک‌تری از srcset دانلود می‌کند. خالی = تمام عرض صفحه.'));
        $this->end_controls_section();

        /* ================= STYLE ================= */
        $this->start_controls_section('oic_style_images', array('label' => 'قاب تصویر', 'tab' => $cm::TAB_STYLE));
        $this->vs('radius', 'گردی گوشه‌ها', '--oic-radius', 0, 200, array('px','%'));
        $this->vc('image_background', 'پس‌زمینه قاب (قبل از لود تصویر)', '--oic-image-bg');
        $this->add_control('image_border', array('label' => 'نوع کادر', 'type' => $cm::SELECT, 'default' => 'none', 'options' => array('none' => 'بدون کادر','solid' => 'Solid','dashed' => 'Dashed','dotted' => 'Dotted','double' => 'Double'), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-border-style:{{VALUE}};')));
        $this->vc('border_color', 'رنگ کادر', '--oic-border-color', array('condition' => array('image_border!' => 'none')));
        $this->vs('border_width', 'ضخامت کادر', '--oic-border-width', 0, 20, array('px'), 1, array('condition' => array('image_border!' => 'none')));
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), array('name' => 'frame_shadow', 'selector' => '{{WRAPPER}} .oic-frame'));
        $this->sw('hover_zoom', 'زوم آرام تصویر در هاور', '');
        $this->end_controls_section();

        $this->start_controls_section('oic_style_content', array('label' => 'متن‌ها', 'tab' => $cm::TAB_STYLE, 'condition' => array('content_layer' => 'yes')));
        $this->heading('st_k', 'متن کوچک بالای تیتر');
        $this->typo('kicker_typo', 'تایپوگرافی', '.oic-kicker');
        $this->vc('kicker_color', 'رنگ', '--oic-k-color');
        $this->heading('st_h', 'تیتر');
        $this->typo('heading_typo', 'تایپوگرافی', '.oic-heading');
        $this->vc('heading_color', 'رنگ', '--oic-h-color');
        $this->add_group_control(\Elementor\Group_Control_Text_Shadow::get_type(), array('name' => 'heading_shadow', 'selector' => '{{WRAPPER}} .oic-heading'));
        $this->vs('heading_gap', 'فاصله زیر تیتر', '--oic-h-gap', 0, 80, array('px','em'));
        $this->vc('accent_color', 'رنگ خط کنار تیتر', '--oic-accent', array('condition' => array('skin' => 'notch_wide')));
        $this->heading('st_s', 'زیرتیتر');
        $this->typo('sub_typo', 'تایپوگرافی', '.oic-sub');
        $this->vc('sub_color', 'رنگ', '--oic-s-color');
        $this->heading('st_d', 'توضیح');
        $this->typo('desc_typo', 'تایپوگرافی', '.oic-desc');
        $this->vc('desc_color', 'رنگ', '--oic-d-color');
        $this->vs('desc_gap', 'فاصله زیر توضیح', '--oic-d-gap', 0, 80, array('px','em'));
        $this->vc('scrim_color', 'رنگ لایه تیره', '--oic-scrim', array('separator' => 'before', 'condition' => array('scrim!' => 'none')));
        $this->end_controls_section();

        $this->start_controls_section('oic_style_button', array('label' => 'دکمه', 'tab' => $cm::TAB_STYLE, 'condition' => array('content_layer' => 'yes')));
        $this->typo('btn_typo', 'تایپوگرافی', '.oic-btn');
        $this->start_controls_tabs('btn_tabs');
        $this->start_controls_tab('btn_normal', array('label' => 'عادی'));
        $this->vc('btn_bg', 'پس‌زمینه', '--oic-btn-bg');
        $this->vc('btn_color', 'رنگ متن', '--oic-btn-color');
        $this->vc('btn_ic_bg', 'پس‌زمینه آیکون', '--oic-btn-ic-bg');
        $this->vc('btn_ic_color', 'رنگ آیکون', '--oic-btn-ic-color');
        $this->end_controls_tab();
        $this->start_controls_tab('btn_hover', array('label' => 'هاور'));
        $this->vc('btn_hbg', 'پس‌زمینه', '--oic-btn-hbg');
        $this->vc('btn_hcolor', 'رنگ متن', '--oic-btn-hcolor');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_responsive_control('btn_padding', array('label' => 'فاصله داخلی', 'type' => $cm::DIMENSIONS, 'size_units' => array('px','em'), 'separator' => 'before', 'selectors' => array('{{WRAPPER}} .oic-btn' => 'padding:{{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};')));
        $this->vs('btn_radius', 'گردی دکمه', '--oic-btn-radius', 0, 100, array('px','%'));
        $this->vs('btn_border_w', 'ضخامت حاشیه', '--oic-btn-border-w', 0, 6);
        $this->vc('btn_border', 'رنگ حاشیه', '--oic-btn-border');
        $this->add_group_control(\Elementor\Group_Control_Box_Shadow::get_type(), array('name' => 'btn_shadow', 'selector' => '{{WRAPPER}} .oic-btn'));
        $this->vs('btn_ic_size', 'اندازه آیکون', '--oic-btn-ic-size', 10, 80, array('px','em'));
        $this->end_controls_section();

        $this->start_controls_section('oic_style_arrows', array('label' => 'فلش‌ها', 'tab' => $cm::TAB_STYLE, 'condition' => array('navigation' => array('both','arrows'))));
        $this->add_control('arrow_position', array('label' => 'موقعیت فلش', 'type' => $cm::SELECT, 'default' => 'inside', 'options' => array('inside' => 'داخل اسلایدر','outside' => 'بیرون اسلایدر')));
        $this->add_control('arrows_visibility', array('label' => 'نمایش فلش‌ها', 'type' => $cm::SELECT, 'default' => '', 'options' => array('' => 'پیش‌فرض استایل','always' => 'همیشه','hover' => 'فقط هنگام هاور (در موبایل مخفی)')));
        $this->add_responsive_control('arrows_display', array('label' => 'نمایش در دستگاه', 'type' => $cm::SELECT, 'options' => array('' => 'نمایش','none' => 'مخفی'), 'selectors' => array('{{WRAPPER}} .oic-arrow' => 'display:{{VALUE}} !important;')));
        $this->vs('arrow_size', 'اندازه دکمه', '--oic-arrow-size', 16, 120);
        $this->vs('arrow_icon_size', 'اندازه آیکون', '--oic-arrow-icon-size', 6, 80);
        $this->add_control('arrow_stroke', array('label' => 'ضخامت خط آیکون پیش‌فرض', 'type' => $cm::SLIDER, 'range' => array('px' => array('min' => 1, 'max' => 4, 'step' => .1)), 'selectors' => array('{{WRAPPER}} .oic-carousel' => '--oic-arrow-stroke:{{SIZE}};')));
        $this->vs('arrow_offset', 'فاصله افقی از لبه', '--oic-arrow-offset', -60, 200);
        $this->vs('arrow_v', 'موقعیت عمودی', '--oic-arrow-v', 0, 100, array('%'));
        $this->vs('arrow_radius', 'گردی', '--oic-arrow-radius', 0, 100, array('px','%'));
        $this->start_controls_tabs('arrow_tabs');
        $this->start_controls_tab('arrow_normal', array('label' => 'عادی'));
        $this->vc('arrow_color', 'رنگ آیکون', '--oic-arrow-color');
        $this->vc('arrow_bg', 'پس‌زمینه', '--oic-arrow-bg');
        $this->vc('arrow_border_color', 'رنگ حاشیه', '--oic-arrow-border');
        $this->end_controls_tab();
        $this->start_controls_tab('arrow_hover_tab', array('label' => 'هاور'));
        $this->vc('arrow_hover_color', 'رنگ آیکون', '--oic-arrow-hover-color');
        $this->vc('arrow_hover_bg', 'پس‌زمینه', '--oic-arrow-hover-bg');
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->vs('arrow_border_w', 'ضخامت حاشیه', '--oic-arrow-border-w', 0, 6, array('px'), .5, array('separator' => 'before'));
        $this->end_controls_section();

        $this->start_controls_section('oic_style_dots', array('label' => 'صفحه‌بندی', 'tab' => $cm::TAB_STYLE, 'condition' => array('navigation' => array('both','dots'))));
        $this->add_control('dots_position', array('label' => 'جایگاه', 'type' => $cm::SELECT, 'default' => 'inside', 'options' => array('inside' => 'روی تصویر','outside' => 'زیر تصاویر')));
        $this->add_control('dots_align', array('label' => 'تراز', 'type' => $cm::CHOOSE, 'default' => 'center', 'toggle' => false, 'options' => array('start' => array('title' => 'شروع', 'icon' => 'eicon-text-align-right'), 'center' => array('title' => 'وسط', 'icon' => 'eicon-text-align-center'), 'end' => array('title' => 'پایان', 'icon' => 'eicon-text-align-left'))));
        $this->add_responsive_control('dots_display', array('label' => 'نمایش در دستگاه', 'type' => $cm::SELECT, 'options' => array('' => 'نمایش','none' => 'مخفی'), 'selectors' => array('{{WRAPPER}} .oic-pagination' => 'display:{{VALUE}} !important;')));
        $this->vs('dots_size', 'اندازه نقاط', '--oic-dot-size', 2, 30);
        $this->vs('active_dot_length', 'طول نقطه فعال (ضریب)', '--oic-active-length', 1, 6, array('px'), .1, array(), '{{SIZE}}');
        $this->vc('dot_color', 'رنگ نقاط', '--oic-dot-color');
        $this->vc('dot_active', 'رنگ فعال', '--oic-active');
        $this->vc('pagination_bg', 'پس‌زمینه', '--oic-pagination-bg');
        $this->vc('pagination_color', 'رنگ متن (شماره)', '--oic-pagination-color', array('condition' => array('pagination' => 'fraction')));
        $this->vs('pagination_gap', 'فاصله نقاط از هم', '--oic-pagination-gap', 0, 40);
        $this->vs('pagination_bottom', 'فاصله از پایین', '--oic-pagination-bottom', 0, 120);
        $this->vs('pagination_side', 'فاصله از کنار (تراز شروع/پایان)', '--oic-pagination-side', 0, 300);
        $this->vs('pagination_pad_x', 'فاصله داخلی افقی', '--oic-pagination-pad-x', 0, 40);
        $this->vs('pagination_pad_y', 'فاصله داخلی عمودی', '--oic-pagination-pad-y', 0, 30);
        $this->vs('pagination_radius', 'گردی پس‌زمینه', '--oic-pagination-radius', 0, 100);
        $this->end_controls_section();

        $this->start_controls_section('oic_style_captions', array('label' => 'کپشن و هاور', 'tab' => $cm::TAB_STYLE));
        $this->vc('caption_color', 'رنگ کپشن', '--oic-caption-color');
        $this->vs('caption_size', 'اندازه فونت کپشن', '--oic-caption-size', 10, 48);
        $this->vc('overlay_color', 'رنگ متن هاور', '--oic-overlay-color');
        $this->vc('overlay_bg', 'رنگ پوشش هاور', '--oic-overlay-bg');
        $this->end_controls_section();
    }

    /* ------------------------------------------------------------------ render helpers */

    private function wp_size($s) {
        $req = $s['image_size'] ?? 'large';
        if ($req === 'custom') {
            return array(max(100, min(4000, (int) ($s['custom_image_width'] ?? 1200))), max(100, min(4000, (int) ($s['custom_image_height'] ?? 600))));
        }
        return in_array($req, array('thumbnail','medium','medium_large','large','1536x1536','2048x2048','full'), true) ? $req : 'large';
    }

    /** `sizes` for desktop/tablet/mobile so the browser downloads the smallest srcset candidate. */
    private function sizes_attr($s, $fade) {
        $show = function ($k, $d) use ($s, $fade) {
            if ($fade) { return 1.0; }
            $v = (float) ($s[$k] ?? 0);
            return $v >= 1 ? $v : $d;
        };
        $d = $show('show', 1); $t = $show('show_tablet', $d); $m = $show('show_mobile', $t);
        $mb = $this->breakpoint('mobile', 767); $tb = $this->breakpoint('tablet', 1024);
        $vw = function ($n) { return max(10, min(100, (int) ceil(100 / $n))); };
        $desk = $vw($d) . 'vw';
        $cap = (int) ($s['sizes_max'] ?? 0);
        if ($cap > 0) { $desk = 'min(' . $desk . ', ' . (int) ceil($cap / $d) . 'px)'; }
        return array(
            'all' => sprintf('(max-width: %dpx) %dvw, (max-width: %dpx) %dvw, %s', $mb, $vw($m), $tb, $vw($t), $desk),
            'mobile' => $vw($m) . 'vw',
            'mb' => $mb,
        );
    }

    /** Semantic, responsive image: native WP srcset + optional mobile art-direction source. */
    private function photo($id, $url, $alt, $i, $s, $ctx, $mobile = array()) {
        $lazy = ($s['lazy'] ?? 'yes') === 'yes' && $i >= $ctx['eager'];
        $attrs = array('class' => 'oic-img', 'alt' => $alt, 'decoding' => 'async', 'loading' => $lazy ? 'lazy' : 'eager', 'sizes' => $ctx['sizes']['all']);
        if ($i === 0 && ($s['priority_first'] ?? 'yes') === 'yes') { $attrs['fetchpriority'] = 'high'; }
        elseif ($i > 0 && $ctx['fade']) { $attrs['fetchpriority'] = 'low'; }
        $size = $ctx['size'];
        $html = '';
        if ($id) { $html = wp_get_attachment_image($id, $size, false, $attrs); }
        if (!$html && $url) {
            $html = '<img class="oic-img" src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="' . esc_attr($attrs['loading']) . '" decoding="async"'
                . (isset($attrs['fetchpriority']) ? ' fetchpriority="' . esc_attr($attrs['fetchpriority']) . '"' : '') . '>';
        }
        if (!$html) { return ''; }
        $mid = absint($mobile['id'] ?? 0); $murl = (string) ($mobile['url'] ?? '');
        if (!$mid && !$murl) { return $html; }
        $source = '';
        if ($mid) {
            $src = wp_get_attachment_image_src($mid, $size);
            $srcset = wp_get_attachment_image_srcset($mid, $size);
            if ($src) {
                $source = '<source media="(max-width: ' . (int) $ctx['sizes']['mb'] . 'px)" srcset="' . esc_attr($srcset ?: $src[0]) . '" sizes="' . esc_attr($ctx['sizes']['mobile']) . '"'
                    . (!empty($src[1]) ? ' width="' . (int) $src[1] . '" height="' . (int) $src[2] . '"' : '') . '>';
            }
        }
        if (!$source && $murl) {
            $source = '<source media="(max-width: ' . (int) $ctx['sizes']['mb'] . 'px)" srcset="' . esc_url($murl) . '">';
        }
        return '<picture>' . $source . $html . '</picture>';
    }

    private function render_arrow($kind, $s) {
        echo '<button type="button" class="oic-arrow oic-' . esc_attr($kind) . '" aria-label="' . esc_attr($kind === 'prev' ? 'اسلاید قبلی' : 'اسلاید بعدی') . '"><span class="oic-arrow-icon" aria-hidden="true">';
        $icon = $s[$kind . '_icon'] ?? array();
        if (!empty($icon['value'])) { \Elementor\Icons_Manager::render_icon($icon, array('aria-hidden' => 'true')); }
        else { echo '<svg class="oic-default-icon" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($kind === 'prev' ? 'm15 18-6-6 6-6' : 'm9 18 6-6-6-6') . '"/></svg>'; }
        echo '</span></button>';
    }

    private function render_play() {
        echo '<button type="button" class="oic-play" aria-pressed="false" aria-label="توقف / پخش خودکار">'
            . '<svg class="oic-i-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>'
            . '<svg class="oic-i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z"/></svg></button>';
    }

    private function render_pagination($type, $n) {
        echo '<div class="oic-pagination oic-pagination-' . esc_attr($type) . '">';
        if ($type === 'dots') {
            for ($i = 0; $i < $n; $i++) {
                echo '<button type="button" class="oic-dot' . ($i === 0 ? ' is-active' : '') . '" data-index="' . $i . '" aria-label="' . esc_attr(sprintf('نمایش اسلاید %d', $i + 1)) . '" aria-current="' . ($i === 0 ? 'true' : 'false') . '"><span></span></button>';
            }
        } elseif ($type === 'fraction') {
            echo '<span class="oic-current">1</span>&nbsp;/&nbsp;<span class="oic-total">' . (int) $n . '</span>';
        } else {
            echo '<div class="oic-progress" role="progressbar" aria-label="پیشرفت اسلایدر" aria-valuemin="1" aria-valuemax="' . (int) $n . '" aria-valuenow="1"><div class="oic-progress-fill" style="--oic-p:' . esc_attr(round(100 / $n, 3)) . '%"></div></div>';
        }
        echo '</div>';
    }

    private function kses_inline($text) {
        return wp_kses((string) $text, array('br' => array(), 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'span' => array('class' => array(), 'style' => array()), 'mark' => array()));
    }

    private function render_button($text, $href, $link_attr, $mode, $glyph, $s, $hidden) {
        $cls = 'oic-btn' . ($mode !== 'none' ? ' oic-btn--ic' . ($mode === 'start' ? ' oic-btn--ic-start' : '') : '');
        $icon = '';
        if ($mode !== 'none') {
            $ic = $s['button_icon'] ?? array();
            if (!empty($ic['value'])) {
                ob_start(); \Elementor\Icons_Manager::render_icon($ic, array('aria-hidden' => 'true')); $icon = ob_get_clean();
            } elseif ($glyph === 'arrow') {
                $icon = '<svg class="oic-default-icon oic-ic-solid" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12 10 6.5v3.75h11v3.5H10v3.75z"/></svg>';
            } else {
                $icon = '<svg class="oic-default-icon" viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 17-5-5 5-5M18 17l-5-5 5-5"/></svg>';
            }
            $icon = '<span class="oic-btn-ic" aria-hidden="true">' . $icon . '</span>';
        }
        $inner = '<span class="oic-btn-t">' . esc_html($text) . '</span>' . $icon;
        if ($href) {
            echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($href) . '"' . $link_attr . ($hidden ? ' tabindex="-1"' : '') . '>' . $inner . '</a>'; // phpcs:ignore -- escaped
        } else {
            echo '<span class="' . esc_attr($cls) . '">' . $inner . '</span>'; // phpcs:ignore -- escaped
        }
    }

    /* ------------------------------------------------------------------ render */

    protected function render(): void {
        $s = $this->get_settings_for_display();
        $gallery = ($s['source'] ?? 'gallery') === 'gallery';
        $raw = $gallery ? ($s['gallery'] ?? array()) : ($s['slides'] ?? array());

        $items = array();
        foreach ((array) $raw as $slide) {
            $image = $gallery ? $slide : ($slide['image'] ?? array());
            $id = absint($image['id'] ?? 0); $url = (string) ($image['url'] ?? '');
            if (!$id && $url === '') { continue; }
            $items[] = array('id' => $id, 'url' => $url, 'slide' => $gallery ? array() : $slide);
        }
        if (!$items) {
            if ($this->is_edit()) {
                echo '<div class="oic-empty" role="note">برای نمایش اسلایدر، تصاویر را از بخش «تصاویر و اسلایدها» انتخاب کنید.</div>';
            }
            return;
        }

        $n = count($items);
        $skin = in_array($s['skin'] ?? 'classic', self::SKINS, true) ? $s['skin'] : 'classic';
        $sd = self::SKIN_DEFAULTS[$skin];
        $fade = ($s['effect'] ?? 'slide') === 'fade';
        $nav = $s['navigation'] ?? 'both';
        $dots = ($nav === 'dots' || $nav === 'both') && $n > 1;
        $arrows = ($nav === 'arrows' || $nav === 'both') && $n > 1;
        $ptype = in_array($s['pagination'] ?? 'dots', array('dots','fraction','progress'), true) ? $s['pagination'] : 'dots';
        $dir = ($s['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
        $autoplay = ($s['autoplay'] ?? '') === 'yes' && $n > 1;
        $speed = max(0, min(3000, (int) ($s['speed'] ?? 450)));
        $delay = max(1000, min(60000, (int) ($s['delay'] ?? 5000)));
        $ease = self::EASE[$s['easing'] ?? 'smooth'] ?? self::EASE['smooth'];
        $hmode = in_array($s['height_mode'] ?? 'ratio', array('ratio','fixed','auto'), true) ? $s['height_mode'] : 'ratio';
        $content_on = ($s['content_layer'] ?? 'yes') === 'yes';
        $gcontent = $gallery ? ($s['gallery_content'] ?? 'none') : 'none';
        $align = in_array($s['dots_align'] ?? 'center', array('start','center','end'), true) ? $s['dots_align'] : 'center';
        $arrows_vis = in_array($s['arrows_visibility'] ?? '', array('always','hover'), true) ? $s['arrows_visibility'] : $sd['arrows'];
        $icon_mode = in_array($s['button_icon_mode'] ?? '', array('end','start','none'), true) ? $s['button_icon_mode'] : $sd['icon'];
        $notch = $sd['notch'] && ($s['notch'] ?? 'yes') === 'yes';
        $page_rtl = function_exists('is_rtl') && is_rtl();

        $show_max = 1;
        if (!$fade) {
            foreach (array('show','show_tablet','show_mobile') as $k) { $show_max = max($show_max, (float) ($s[$k] ?? 1)); }
        }
        $ctx = array(
            'eager' => $fade ? 1 : max(1, (int) ceil($show_max)),
            'fade' => $fade,
            'size' => $this->wp_size($s),
            'sizes' => $this->sizes_attr($s, $fade),
        );

        $config = array(
            'n' => $n, 'fx' => $fade ? 'fade' : 'slide', 'dir' => $dir,
            'loop' => ($s['loop'] ?? 'yes') === 'yes',
            'autoplay' => $autoplay, 'delay' => $delay, 'speed' => $speed,
            'ph' => ($s['pause_hover'] ?? 'yes') === 'yes',
            'pi' => ($s['pause_interaction'] ?? 'yes') === 'yes',
            'touch' => ($s['touch'] ?? 'yes') === 'yes',
            'wheel' => ($s['wheel'] ?? '') === 'yes',
        );
        if ($this->is_edit()) { $config['edit'] = 1; }

        $cls = array('oic-carousel', 'oic-skin-' . $skin, 'oic-fx-' . $config['fx'], 'oic-h-' . $hmode, 'oic-pga-' . $align);
        $cls[] = ($s['stretch'] ?? 'yes') === 'yes' ? 'oic-stretch' : 'oic-natural';
        if ($dir === 'rtl') { $cls[] = 'oic-rtl'; }
        if ($page_rtl) { $cls[] = 'oic-trtl'; }
        if (($s['arrow_position'] ?? 'inside') === 'outside') { $cls[] = 'oic-arrows-outside'; }
        if ($arrows_vis === 'hover') { $cls[] = 'oic-arrows-hover'; }
        if ($autoplay && ($s['autoplay_progress'] ?? '') === 'yes') { $cls[] = 'oic-ap-progress'; }
        if (($s['hover_zoom'] ?? '') === 'yes') { $cls[] = 'oic-hover-zoom'; }
        if ($content_on) {
            $cls[] = 'oic-ca-' . preg_replace('/[^a-z]/', '', (string) ($s['content_animation'] ?? 'up'));
            $cls[] = 'oic-scrim-' . preg_replace('/[^a-z]/', '', (string) ($s['scrim'] ?? 'none'));
        }
        $style = '--oic-sp:' . $speed . 'ms;--oic-delay:' . $delay . 'ms;--oic-ease:' . $ease;
        // Mobile art-direction: frame follows the mobile image ratio unless a mobile ratio was set manually.
        if (!$gallery && $hmode === 'ratio' && empty($s['ratio_mobile'])) {
            $m0 = $items[0]['slide']['image_mobile']['id'] ?? 0;
            $md = $m0 ? wp_get_attachment_image_src((int) $m0, $ctx['size']) : false;
            if ($md && !empty($md[1]) && !empty($md[2])) { $cls[] = 'oic-mratio'; $style = '--oic-mratio:' . (int) $md[1] . ' / ' . (int) $md[2] . ';' . $style; }
        }

        $uid = 'pce-' . sanitize_html_class($this->get_id());
        $tag = in_array($s['heading_tag'] ?? 'div', array('h1','h2','h3','h4','p','div'), true) ? $s['heading_tag'] : 'div';
        $first_tag = in_array($s['first_heading_tag'] ?? '', array('h1','h2'), true) ? $s['first_heading_tag'] : $tag;
        $pos_default = array_key_exists($s['content_pos'] ?? '', $this->positions()) ? $s['content_pos'] : $sd['pos'];
        $link_mode = $s['link_mode'] ?? 'custom';

        echo '<div class="' . esc_attr(implode(' ', $cls)) . '" id="' . esc_attr($uid) . '" data-oic="' . esc_attr(wp_json_encode($config)) . '" style="' . esc_attr($style) . '" role="region" aria-roledescription="carousel" aria-label="اسلایدر تصاویر">';
        echo '<div class="oic-stage">';
        echo '<div class="oic-viewport" tabindex="0" aria-live="' . ($autoplay ? 'off' : 'polite') . '"><div class="oic-track">';

        foreach ($items as $i => $it) {
            $slide = $it['slide']; $id = $it['id']; $url = $it['url'];
            $attachment = $id ? get_post($id) : null;
            $alt = $gallery ? '' : trim((string) ($slide['alt'] ?? ''));
            if ($alt === '' && $id) { $alt = (string) get_post_meta($id, '_wp_attachment_image_alt', true); }
            $title = $gallery ? '' : trim((string) ($slide['title'] ?? ''));
            if ($title === '' && $attachment) { $title = $attachment->post_title; }
            $wp_caption = $attachment ? $attachment->post_excerpt : '';

            $kicker = ''; $heading = ''; $sub = ''; $desc = ''; $btn = '';
            if ($content_on) {
                if ($gallery) {
                    if ($gcontent !== 'none' && $attachment) { $heading = $attachment->post_title; }
                    if ($gcontent === 'title_caption') { $desc = $wp_caption; }
                } else {
                    $kicker = trim((string) ($slide['kicker'] ?? ''));
                    $heading = trim((string) ($slide['heading'] ?? ''));
                    $sub = trim((string) ($slide['subtitle'] ?? ''));
                    $desc = trim((string) ($slide['description'] ?? ''));
                    $btn = trim((string) ($slide['button_text'] ?? ''));
                }
            }
            if ($alt === '' && $heading !== '') { $alt = wp_strip_all_tags($heading); }

            $href = ''; $link_attr = '';
            if ($link_mode === 'file') {
                $href = $id ? (string) wp_get_attachment_url($id) : $url;
                if ($href) { $link_attr = ' data-elementor-open-lightbox="' . (($s['lightbox'] ?? 'yes') === 'yes' ? 'yes' : 'no') . '" data-elementor-lightbox-slideshow="' . esc_attr($uid) . '"'; }
            } elseif ($link_mode === 'custom' && !$gallery) {
                $href = (string) ($slide['link']['url'] ?? '');
                if ($href) {
                    $ext = !empty($slide['link']['is_external']);
                    $rels = array();
                    if (!empty($slide['link']['nofollow'])) { $rels[] = 'nofollow'; }
                    if ($ext) { $link_attr .= ' target="_blank"'; $rels[] = 'noopener'; }
                    if ($rels) { $link_attr .= ' rel="' . esc_attr(implode(' ', $rels)) . '"'; }
                }
            }
            $button_link = $href && $link_mode === 'custom' && !$gallery && ($s['link_area'] ?? 'frame') === 'button' && $btn !== '';
            $frame_link = $href && !$button_link;

            $label = '';
            switch ($s['caption_mode'] ?? 'none') {
                case 'custom': $label = $gallery ? '' : trim((string) ($slide['caption'] ?? '')); break;
                case 'title': $label = $title; break;
                case 'caption': $label = $wp_caption; break;
                case 'description': $label = $attachment ? $attachment->post_content : ''; break;
            }
            $overlay = $s['overlay'] ?? 'none';
            $overlay_text = $overlay === 'title' ? $title : ($overlay === 'caption' ? ($gallery ? $wp_caption : (trim((string) ($slide['caption'] ?? '')) ?: $wp_caption)) : '');

            $scls = 'oic-slide' . ($i === 0 ? ' is-active' : '') . (!$gallery && !empty($slide['_id']) ? ' elementor-repeater-item-' . sanitize_html_class($slide['_id']) : '');
            echo '<div class="' . esc_attr($scls) . '" role="group" aria-roledescription="slide" aria-label="' . esc_attr(($i + 1) . ' / ' . $n) . '"' . ($i > 0 ? ' aria-hidden="true"' : '') . '><div class="oic-frame">';
            if ($frame_link) {
                echo '<a class="oic-media-link" href="' . esc_url($href) . '"' . $link_attr . ' aria-label="' . esc_attr(wp_strip_all_tags($heading ?: ($alt ?: ($title ?: sprintf('اسلاید %d', $i + 1))))) . '"' . ($i > 0 ? ' tabindex="-1"' : '') . '>'; // phpcs:ignore -- attrs escaped above
            }
            echo $this->photo($id, $url, $alt, $i, $s, $ctx, $gallery ? array() : ($slide['image_mobile'] ?? array())); // phpcs:ignore -- escaped in photo()
            if ($overlay !== 'none') {
                echo '<span class="oic-overlay oic-overlay-' . esc_attr($s['overlay_animation'] ?? 'fade') . '" aria-hidden="true">';
                if ($overlay === 'icon') { echo '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>'; }
                else { echo esc_html($overlay_text); }
                echo '</span>';
            }
            if ($frame_link) { echo '</a>'; }

            if ($kicker !== '' || $heading !== '' || $sub !== '' || $desc !== '' || $btn !== '') {
                $pos = !$gallery && !empty($slide['content_position']) && array_key_exists($slide['content_position'], $this->positions()) ? $slide['content_position'] : $pos_default;
                $htag = $i === 0 ? $first_tag : $tag;
                echo '<div class="oic-content oic-pos-' . esc_attr($pos) . '"><div class="oic-content-in">';
                if ($kicker !== '') { echo '<div class="oic-kicker">' . $this->kses_inline($kicker) . '</div>'; } // phpcs:ignore -- kses'd
                if ($heading !== '' || $sub !== '') {
                    echo '<div class="oic-title">';
                    if ($heading !== '') { echo '<' . $htag . ' class="oic-heading">' . nl2br($this->kses_inline($heading)) . '</' . $htag . '>'; } // phpcs:ignore -- tag whitelisted, content kses'd
                    if ($sub !== '') { echo '<div class="oic-sub">' . $this->kses_inline($sub) . '</div>'; } // phpcs:ignore
                    echo '</div>';
                }
                if ($desc !== '') { echo '<div class="oic-desc">' . nl2br($this->kses_inline($desc)) . '</div>'; } // phpcs:ignore
                if ($btn !== '') { $this->render_button($btn, $button_link ? $href : '', $link_attr, $icon_mode, $sd['glyph'], $s, $i > 0); }
                echo '</div></div>';
            }
            echo '</div>';
            if ($label !== '') { echo '<div class="oic-caption">' . esc_html($label) . '</div>'; }
            echo '</div>';
        }
        echo '</div></div>';

        if ($notch) { echo '<span class="oic-notch" aria-hidden="true"></span>'; }
        if ($arrows) { $this->render_arrow('prev', $s); $this->render_arrow('next', $s); }
        if ($autoplay && ($s['play_button'] ?? '') === 'yes') { $this->render_play(); }
        $outside = ($s['dots_position'] ?? 'inside') === 'outside';
        if ($dots && !$outside) { $this->render_pagination($ptype, $n); }
        echo '</div>'; // stage
        if ($dots && $outside) { $this->render_pagination($ptype, $n); }
        echo '</div>';
    }
}
