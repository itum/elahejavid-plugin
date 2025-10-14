<?php
/**
 * هماهنگی با افزونه Tutor LMS
 * این فایل قابلیت‌های هماهنگی با Tutor LMS را فراهم می‌کند
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class TutorLMSIntegration {
    
    public function __construct() {
        // اضافه کردن منوی ادمین همیشه (حتی اگر Tutor LMS فعال نباشد)
        add_action('admin_menu', array($this, 'add_tutor_integration_menu'));
        
        // اضافه کردن شورت‌کد همیشه (حتی اگر Tutor LMS فعال نباشد)
        add_shortcode('oa_tutor_quiz', array($this, 'tutor_quiz_shortcode'));
        
        // بررسی وجود افزونه Tutor LMS
        add_action('plugins_loaded', array($this, 'check_tutor_lms'));
        
        // اضافه کردن hooks برای Tutor LMS
        add_action('init', array($this, 'init_tutor_hooks'));
        
        // بارگذاری استایل‌ها و اسکریپت‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tutor_styles'));
    }
    
    /**
     * بارگذاری استایل‌ها و اسکریپت‌های مخصوص Tutor LMS
     */
    public function enqueue_tutor_styles() {
        $settings = $this->get_tutor_settings();
        
        if (!$settings['enable_integration']) {
            return;
        }
        
        // بارگذاری استایل‌های مخصوص Tutor LMS
        wp_enqueue_style(
            'oa-tutor-integration-style',
            OA_PLUGIN_URL . 'assets/css/tutor-lms-integration.css',
            array(),
            OA_PLUGIN_VERSION
        );
        
        // بارگذاری اسکریپت‌های مخصوص Tutor LMS
        wp_enqueue_script(
            'oa-tutor-integration-script',
            OA_PLUGIN_URL . 'assets/js/tutor-lms-integration.js',
            array('jquery'),
            OA_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * بررسی وجود افزونه Tutor LMS
     */
    public function check_tutor_lms() {
        if (!$this->is_tutor_lms_active()) {
            return;
        }
        
        // فعال کردن هماهنگی با Tutor LMS
        $this->enable_tutor_integration();
    }
    
    /**
     * بررسی فعال بودن افزونه Tutor LMS با روش‌های مختلف
     */
    private function is_tutor_lms_active() {
        // روش 1: بررسی کلاس TUTOR
        if (class_exists('TUTOR')) {
            return true;
        }
        
        // روش 2: بررسی تابع tutor
        if (function_exists('tutor')) {
            return true;
        }
        
        // روش 3: بررسی کلاس Tutor
        if (class_exists('Tutor')) {
            return true;
        }
        
        // روش 4: بررسی افزونه فعال در WordPress
        if (is_plugin_active('tutor/tutor.php')) {
            return true;
        }
        
        // روش 5: بررسی افزونه فعال با نام کامل
        if (is_plugin_active('tutor-lms/tutor.php')) {
            return true;
        }
        
        // روش 6: بررسی وجود فایل اصلی Tutor LMS
        if (file_exists(WP_PLUGIN_DIR . '/tutor/tutor.php')) {
            return true;
        }
        
        // روش 7: بررسی وجود فایل اصلی با نام کامل
        if (file_exists(WP_PLUGIN_DIR . '/tutor-lms/tutor.php')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * فعال کردن هماهنگی با Tutor LMS
     */
    private function enable_tutor_integration() {
        // اضافه کردن فیلتر برای نمایش آزمون در محتوای درس
        add_filter('tutor_course_contents', array($this, 'add_quiz_to_course_content'), 10, 2);
        
        // اضافه کردن اکشن برای ذخیره نتایج آزمون در Tutor LMS
        add_action('oa_quiz_completed', array($this, 'save_quiz_result_to_tutor'), 10, 2);
        
        // اضافه کردن فیلتر برای نمایش نتایج در پروفایل کاربر
        add_filter('tutor_profile_tabs', array($this, 'add_quiz_results_tab'));
    }
    
    /**
     * اضافه کردن منوی ادمین برای تنظیمات هماهنگی
     */
    public function add_tutor_integration_menu() {
        add_submenu_page(
            'obesity-assessment',
            'هماهنگی با Tutor LMS',
            'هماهنگی با Tutor LMS',
            'manage_options',
            'oa-tutor-integration',
            array($this, 'tutor_integration_page')
        );
    }
    
    /**
     * صفحه تنظیمات هماهنگی با Tutor LMS
     */
    public function tutor_integration_page() {
        // بررسی وجود افزونه Tutor LMS
        $tutor_lms_active = $this->is_tutor_lms_active();
        
        // ذخیره تنظیمات
        if (isset($_POST['save_tutor_settings'])) {
            $this->save_tutor_settings();
        }
        
        $settings = $this->get_tutor_settings();
        ?>
        <div class="wrap">
            <h1>هماهنگی با Tutor LMS</h1>
            
            <?php if (!$tutor_lms_active): ?>
            <div class="notice notice-warning">
                <p><strong>توجه:</strong> افزونه Tutor LMS نصب نشده یا فعال نیست. برای استفاده از قابلیت‌های هماهنگی، ابتدا افزونه Tutor LMS را نصب و فعال کنید.</p>
                
                <?php if (current_user_can('manage_options')): ?>
                <details style="margin-top: 10px;">
                    <summary style="cursor: pointer; font-weight: bold;">اطلاعات دیباگ (برای توسعه‌دهندگان)</summary>
                    <div style="background: #f0f0f0; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 12px;">
                        <p><strong>بررسی کلاس‌ها:</strong></p>
                        <ul>
                            <li>class_exists('TUTOR'): <?php echo class_exists('TUTOR') ? '✅ true' : '❌ false'; ?></li>
                            <li>class_exists('Tutor'): <?php echo class_exists('Tutor') ? '✅ true' : '❌ false'; ?></li>
                            <li>function_exists('tutor'): <?php echo function_exists('tutor') ? '✅ true' : '❌ false'; ?></li>
                        </ul>
                        
                        <p><strong>بررسی افزونه‌ها:</strong></p>
                        <ul>
                            <li>is_plugin_active('tutor/tutor.php'): <?php echo is_plugin_active('tutor/tutor.php') ? '✅ true' : '❌ false'; ?></li>
                            <li>is_plugin_active('tutor-lms/tutor.php'): <?php echo is_plugin_active('tutor-lms/tutor.php') ? '✅ true' : '❌ false'; ?></li>
                        </ul>
                        
                        <p><strong>بررسی فایل‌ها:</strong></p>
                        <ul>
                            <li>WP_PLUGIN_DIR/tutor/tutor.php: <?php echo file_exists(WP_PLUGIN_DIR . '/tutor/tutor.php') ? '✅ موجود' : '❌ موجود نیست'; ?></li>
                            <li>WP_PLUGIN_DIR/tutor-lms/tutor.php: <?php echo file_exists(WP_PLUGIN_DIR . '/tutor-lms/tutor.php') ? '✅ موجود' : '❌ موجود نیست'; ?></li>
                        </ul>
                        
                        <p><strong>افزونه‌های فعال:</strong></p>
                        <?php
                        $active_plugins = get_option('active_plugins', array());
                        $tutor_plugins = array_filter($active_plugins, function($plugin) {
                            return strpos($plugin, 'tutor') !== false;
                        });
                        
                        if (!empty($tutor_plugins)) {
                            echo '<ul>';
                            foreach ($tutor_plugins as $plugin) {
                                echo '<li>✅ ' . $plugin . '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p>❌ هیچ افزونه Tutor یافت نشد</p>';
                        }
                        ?>
                    </div>
                </details>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="notice notice-success">
                <p><strong>✅ عالی!</strong> افزونه Tutor LMS فعال است و هماهنگی در دسترس است.</p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('oa_tutor_settings', 'oa_tutor_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">فعال کردن هماهنگی</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_tutor_integration" value="1" 
                                       <?php checked($settings['enable_integration'], 1); ?>>
                                فعال کردن هماهنگی با Tutor LMS
                                <?php if (!$tutor_lms_active): ?>
                                <span style="color: #d63638;">(نیاز به نصب Tutor LMS)</span>
                                <?php endif; ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">نمایش آزمون در درس‌ها</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_quiz_in_lessons" value="1" 
                                       <?php checked($settings['show_in_lessons'], 1); ?>>
                                نمایش آزمون تشخیص چاقی در درس‌های مربوطه
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">ذخیره نتایج در Tutor LMS</th>
                        <td>
                            <label>
                                <input type="checkbox" name="save_results_to_tutor" value="1" 
                                       <?php checked($settings['save_results'], 1); ?>>
                                ذخیره نتایج آزمون در سیستم Tutor LMS
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">نمایش نتایج در پروفایل</th>
                        <td>
                            <label>
                                <input type="checkbox" name="show_results_in_profile" value="1" 
                                       <?php checked($settings['show_in_profile'], 1); ?>>
                                نمایش نتایج آزمون در پروفایل کاربر
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">درس‌های مرتبط</th>
                        <td>
                            <select name="related_courses[]" multiple style="width: 100%; height: 150px;">
                                <?php
                                $courses = $this->get_tutor_courses();
                                $selected_courses = $settings['related_courses'];
                                
                                if (!empty($courses)) {
                                    foreach ($courses as $course) {
                                        $selected = in_array($course->ID, $selected_courses) ? 'selected' : '';
                                        echo '<option value="' . $course->ID . '" ' . $selected . '>' . $course->post_title . '</option>';
                                    }
                                } else {
                                    echo '<option disabled>هیچ درسی یافت نشد</option>';
                                }
                                ?>
                            </select>
                            <p class="description">درس‌هایی که آزمون تشخیص چاقی در آن‌ها نمایش داده شود</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('ذخیره تنظیمات', 'primary', 'save_tutor_settings'); ?>
            </form>
            
            <h2>راهنمای استفاده</h2>
            <div class="oa-tutor-guide">
                <?php if ($tutor_lms_active): ?>
                <h3>شورت‌کدهای موجود:</h3>
                <ul>
                    <li><code>[oa_tutor_quiz]</code> - نمایش آزمون تشخیص چاقی در درس‌های Tutor LMS</li>
                    <li><code>[oa_quiz]</code> - نمایش آزمون مرحله‌ای (پیش‌فرض)</li>
                    <li><code>[oa_quiz_all]</code> - نمایش همه سوالات در یک صفحه</li>
                </ul>
                
                <h3>نحوه استفاده:</h3>
                <ol>
                    <li>درس مورد نظر را در Tutor LMS ویرایش کنید</li>
                    <li>در محتوای درس، شورت‌کد <code>[oa_tutor_quiz]</code> را قرار دهید</li>
                    <li>درس را ذخیره کنید</li>
                    <li>دانشجویان می‌توانند آزمون را در درس مشاهده کنند</li>
                </ol>
                <?php else: ?>
                <h3>برای استفاده از قابلیت‌های هماهنگی:</h3>
                <ol>
                    <li>افزونه Tutor LMS را از مخزن وردپرس نصب کنید</li>
                    <li>افزونه را فعال کنید</li>
                    <li>درس‌های مورد نظر را ایجاد کنید</li>
                    <li>به این صفحه برگردید و تنظیمات را انجام دهید</li>
                </ol>
                
                <h3>شورت‌کدهای فعلی (بدون Tutor LMS):</h3>
                <ul>
                    <li><code>[oa_quiz]</code> - نمایش آزمون مرحله‌ای (پیش‌فرض)</li>
                    <li><code>[oa_quiz_all]</code> - نمایش همه سوالات در یک صفحه</li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .oa-tutor-guide {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .oa-tutor-guide h3 {
            margin-top: 0;
        }
        .oa-tutor-guide ul, .oa-tutor-guide ol {
            margin-left: 20px;
        }
        .oa-tutor-guide code {
            background: #e1e1e1;
            padding: 2px 5px;
            border-radius: 3px;
        }
        </style>
        <?php
    }
    
    /**
     * شورت‌کد برای نمایش آزمون در درس‌های Tutor LMS
     */
    public function tutor_quiz_shortcode($atts) {
        $settings = $this->get_tutor_settings();
        
        if (!$settings['enable_integration']) {
            return '<div class="oa-tutor-quiz-section" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; text-align: center;">
                        <h3>تست تشخیص نوع چاقی</h3>
                        <p style="color: #856404; margin: 10px 0;">⚠️ هماهنگی با Tutor LMS غیرفعال است.</p>
                        <p style="color: #856404; font-size: 14px;">برای فعال‌سازی، به پنل ادمین > تست تشخیص چاقی > هماهنگی با Tutor LMS بروید.</p>
                    </div>';
        }
        
        // بررسی اینکه آیا کاربر در درس مربوطه است یا نه
        if (!$this->is_user_in_related_course()) {
            return '<div class="oa-tutor-quiz-section" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; text-align: center;">
                        <h3>تست تشخیص نوع چاقی</h3>
                        <p style="color: #721c24; margin: 10px 0;">🔒 برای شرکت در این آزمون باید در درس مربوطه ثبت‌نام کرده باشید.</p>
                    </div>';
        }
        
        // نمایش آزمون
        $quiz_content = $this->get_quiz_content();
        return $quiz_content;
    }
    
    /**
     * اضافه کردن آزمون به محتوای درس
     */
    public function add_quiz_to_course_content($content, $course_id) {
        $settings = $this->get_tutor_settings();
        
        if (!$settings['enable_integration'] || !$settings['show_in_lessons']) {
            return $content;
        }
        
        if (!in_array($course_id, $settings['related_courses'])) {
            return $content;
        }
        
        // اضافه کردن آزمون به انتهای محتوا
        $quiz_content = '<div class="oa-tutor-quiz-section">';
        $quiz_content .= '<h3>تست تشخیص نوع چاقی</h3>';
        $quiz_content .= do_shortcode('[oa_quiz]');
        $quiz_content .= '</div>';
        
        return $content . $quiz_content;
    }
    
    /**
     * ذخیره نتایج آزمون در Tutor LMS
     */
    public function save_quiz_result_to_tutor($user_id, $quiz_result) {
        $settings = $this->get_tutor_settings();
        
        if (!$settings['enable_integration'] || !$settings['save_results']) {
            return;
        }
        
        // ذخیره نتایج در متا فیلدهای کاربر
        update_user_meta($user_id, 'oa_quiz_result', $quiz_result);
        update_user_meta($user_id, 'oa_quiz_completed_date', current_time('mysql'));
        
        // ذخیره در جدول نتایج Tutor LMS (اگر وجود داشته باشد)
        if (function_exists('tutor_utils')) {
            $this->save_to_tutor_results_table($user_id, $quiz_result);
        }
    }
    
    /**
     * اضافه کردن تب نتایج آزمون به پروفایل کاربر
     */
    public function add_quiz_results_tab($tabs) {
        $settings = $this->get_tutor_settings();
        
        if (!$settings['enable_integration'] || !$settings['show_in_profile']) {
            return $tabs;
        }
        
        $tabs['quiz_results'] = array(
            'title' => 'نتایج آزمون تشخیص چاقی',
            'method' => array($this, 'display_quiz_results_tab')
        );
        
        return $tabs;
    }
    
    /**
     * نمایش تب نتایج آزمون
     */
    public function display_quiz_results_tab() {
        $user_id = get_current_user_id();
        $quiz_result = get_user_meta($user_id, 'oa_quiz_result', true);
        
        if (!$quiz_result) {
            echo '<p>هنوز آزمون تشخیص چاقی را تکمیل نکرده‌اید.</p>';
            return;
        }
        
        echo '<div class="oa-quiz-results">';
        echo '<h3>نتایج آزمون تشخیص چاقی شما</h3>';
        
        // نمایش نتایج
        $this->display_quiz_results($quiz_result);
        
        echo '</div>';
    }
    
    /**
     * دریافت تنظیمات هماهنگی با Tutor LMS
     */
    private function get_tutor_settings() {
        return wp_parse_args(get_option('oa_tutor_settings', array()), array(
            'enable_integration' => 0,
            'show_in_lessons' => 0,
            'save_results' => 0,
            'show_in_profile' => 0,
            'related_courses' => array()
        ));
    }
    
    /**
     * ذخیره تنظیمات هماهنگی با Tutor LMS
     */
    private function save_tutor_settings() {
        if (!wp_verify_nonce($_POST['oa_tutor_nonce'], 'oa_tutor_settings')) {
            wp_die('خطای امنیتی');
        }
        
        $settings = array(
            'enable_integration' => isset($_POST['enable_tutor_integration']) ? 1 : 0,
            'show_in_lessons' => isset($_POST['show_quiz_in_lessons']) ? 1 : 0,
            'save_results' => isset($_POST['save_results_to_tutor']) ? 1 : 0,
            'show_in_profile' => isset($_POST['show_results_in_profile']) ? 1 : 0,
            'related_courses' => isset($_POST['related_courses']) ? array_map('intval', $_POST['related_courses']) : array()
        );
        
        update_option('oa_tutor_settings', $settings);
        
        echo '<div class="notice notice-success"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
    }
    
    /**
     * دریافت لیست درس‌های Tutor LMS
     */
    private function get_tutor_courses() {
        if (!function_exists('tutor_utils')) {
            return array();
        }
        
        $courses = get_posts(array(
            'post_type' => tutor()->course_post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        return $courses;
    }
    
    /**
     * بررسی اینکه آیا کاربر در درس مربوطه است یا نه
     */
    private function is_user_in_related_course() {
        // اگر کاربر لاگین نکرده، اجازه دسترسی بده (برای مهمان‌ها)
        if (!is_user_logged_in()) {
            return true;
        }
        
        $settings = $this->get_tutor_settings();
        
        // اگر هیچ درسی انتخاب نشده، اجازه دسترسی بده
        if (empty($settings['related_courses'])) {
            return true;
        }
        
        $user_id = get_current_user_id();
        
        // بررسی ثبت‌نام در درس‌های مربوطه
        foreach ($settings['related_courses'] as $course_id) {
            // بررسی با روش‌های مختلف Tutor LMS
            if (function_exists('tutor_utils') && tutor_utils()->is_enrolled($course_id, $user_id)) {
                return true;
            }
            
            // بررسی با کلاس Tutor
            if (class_exists('Tutor') && method_exists('Tutor', 'is_enrolled')) {
                if (Tutor::is_enrolled($course_id, $user_id)) {
                    return true;
                }
            }
            
            // بررسی مستقیم با دیتابیس
            global $wpdb;
            $enrollment = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}tutor_enrolled 
                WHERE course_id = %d AND user_id = %d
            ", $course_id, $user_id));
            
            if ($enrollment > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * دریافت محتوای آزمون
     */
    private function get_quiz_content() {
        ob_start();
        include OA_PLUGIN_PATH . 'templates/tutor-quiz-form.php';
        return ob_get_clean();
    }
    
    /**
     * ذخیره در جدول نتایج Tutor LMS
     */
    private function save_to_tutor_results_table($user_id, $quiz_result) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tutor_quiz_attempts';
        
        // بررسی وجود جدول
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        // ذخیره در جدول Tutor LMS
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'quiz_id' => 0, // آزمون خارجی
                'course_id' => 0,
                'total_questions' => count($quiz_result['group_scores']),
                'total_answered_questions' => count($quiz_result['group_scores']),
                'total_marks' => array_sum($quiz_result['group_scores']),
                'earned_marks' => array_sum($quiz_result['group_scores']),
                'attempt_info' => json_encode($quiz_result),
                'attempt_status' => 'completed',
                'attempt_ip' => $_SERVER['REMOTE_ADDR'],
                'attempt_ended_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * نمایش نتایج آزمون
     */
    private function display_quiz_results($quiz_result) {
        global $wpdb;
        
        if (isset($quiz_result['winning_groups']) && !empty($quiz_result['winning_groups'])) {
            echo '<h4>نوع چاقی شما:</h4>';
            
            foreach ($quiz_result['winning_groups'] as $group_id) {
                $group = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}oa_groups WHERE id = %d",
                    $group_id
                ));
                
                if ($group) {
                    echo '<div class="oa-result-group">';
                    echo '<h5>' . esc_html($group->name) . '</h5>';
                    echo '<p>' . esc_html($group->description) . '</p>';
                    echo '<p><strong>توصیه‌ها:</strong> ' . esc_html($group->tips) . '</p>';
                    echo '</div>';
                }
            }
        }
        
        if (isset($quiz_result['group_scores'])) {
            echo '<h4>جزئیات امتیازات:</h4>';
            echo '<ul>';
            
            foreach ($quiz_result['group_scores'] as $group_id => $score) {
                $group = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}oa_groups WHERE id = %d",
                    $group_id
                ));
                
                if ($group) {
                    echo '<li>' . esc_html($group->name) . ': ' . $score . ' امتیاز</li>';
                }
            }
            echo '</ul>';
        }
    }
}

// راه‌اندازی کلاس هماهنگی با Tutor LMS
new TutorLMSIntegration();
