<?php
/**
 * فایل تست هماهنگی با Tutor LMS
 * این فایل برای تست عملکرد هماهنگی با Tutor LMS استفاده می‌شود
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

class TutorLMSIntegrationTest {
    
    public function __construct() {
        add_action('wp_ajax_oa_test_tutor_integration', array($this, 'test_integration'));
        add_action('wp_ajax_oa_check_tutor_status', array($this, 'check_tutor_status'));
        add_action('wp_ajax_oa_test_quiz_display', array($this, 'test_quiz_display'));
    }
    
    /**
     * تست هماهنگی با Tutor LMS
     */
    public function test_integration() {
        // بررسی دسترسی ادمین
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'عدم دسترسی - کاربر ادمین نیست'));
            return;
        }
        
        $results = array();
        
        // تست 1: بررسی وجود افزونه Tutor LMS
        $results['tutor_lms_exists'] = class_exists('TUTOR');
        
        // تست 2: بررسی وجود کلاس هماهنگی
        $results['integration_class_exists'] = class_exists('TutorLMSIntegration');
        
        // تست 3: بررسی تنظیمات هماهنگی
        $settings = get_option('oa_tutor_settings', array());
        $results['settings_exist'] = !empty($settings);
        
        // تست 4: بررسی وجود جداول دیتابیس
        global $wpdb;
        $results['tables_exist'] = $this->check_database_tables();
        
        // تست 5: بررسی وجود فایل‌های CSS و JS
        $results['assets_exist'] = $this->check_assets_files();
        
        // تست 6: بررسی شورت‌کدها
        $results['shortcodes_registered'] = $this->check_shortcodes();
        
        // تست 7: بررسی hooks
        $results['hooks_registered'] = $this->check_hooks();
        
        wp_send_json_success($results);
    }
    
    /**
     * بررسی وضعیت Tutor LMS
     */
    public function check_tutor_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'عدم دسترسی'));
            return;
        }
        
        $status = array();
        
        // بررسی نسخه Tutor LMS
        if (class_exists('TUTOR')) {
            $status['version'] = defined('TUTOR_VERSION') ? TUTOR_VERSION : 'نامشخص';
            $status['active'] = true;
            
            // بررسی وجود درس‌ها
            $courses = get_posts(array(
                'post_type' => tutor()->course_post_type,
                'posts_per_page' => 5,
                'post_status' => 'publish'
            ));
            
            $status['courses_count'] = count($courses);
            $status['courses'] = array();
            
            foreach ($courses as $course) {
                $status['courses'][] = array(
                    'id' => $course->ID,
                    'title' => $course->post_title,
                    'url' => get_permalink($course->ID)
                );
            }
        } else {
            $status['active'] = false;
            $status['message'] = 'افزونه Tutor LMS نصب نشده است';
        }
        
        wp_send_json_success($status);
    }
    
    /**
     * تست نمایش آزمون
     */
    public function test_quiz_display() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'عدم دسترسی'));
            return;
        }
        
        // تست شورت‌کد
        $shortcode_output = do_shortcode('[oa_tutor_quiz]');
        
        $results = array(
            'shortcode_works' => !empty($shortcode_output),
            'output_length' => strlen($shortcode_output),
            'contains_quiz' => strpos($shortcode_output, 'oa-quiz-container') !== false,
            'contains_form' => strpos($shortcode_output, 'oa-quiz-form') !== false
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * بررسی وجود جداول دیتابیس
     */
    private function check_database_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'oa_groups',
            $wpdb->prefix . 'oa_questions',
            $wpdb->prefix . 'oa_options',
            $wpdb->prefix . 'oa_results'
        );
        
        $existing_tables = 0;
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
                $existing_tables++;
            }
        }
        
        return $existing_tables == count($tables);
    }
    
    /**
     * بررسی وجود فایل‌های CSS و JS
     */
    private function check_assets_files() {
        $css_file = OA_PLUGIN_PATH . 'assets/css/tutor-lms-integration.css';
        $js_file = OA_PLUGIN_PATH . 'assets/js/tutor-lms-integration.js';
        
        return file_exists($css_file) && file_exists($js_file);
    }
    
    /**
     * بررسی ثبت شورت‌کدها
     */
    private function check_shortcodes() {
        global $shortcode_tags;
        
        $required_shortcodes = array(
            'oa_tutor_quiz',
            'oa_quiz',
            'oa_quiz_all'
        );
        
        $registered_count = 0;
        foreach ($required_shortcodes as $shortcode) {
            if (isset($shortcode_tags[$shortcode])) {
                $registered_count++;
            }
        }
        
        return $registered_count == count($required_shortcodes);
    }
    
    /**
     * بررسی ثبت hooks
     */
    private function check_hooks() {
        global $wp_filter;
        
        $required_hooks = array(
            'oa_quiz_completed',
            'tutor_course_contents',
            'tutor_profile_tabs'
        );
        
        $registered_count = 0;
        foreach ($required_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $registered_count++;
            }
        }
        
        return $registered_count > 0;
    }
}

// راه‌اندازی کلاس تست
new TutorLMSIntegrationTest();

/**
 * شورت‌کد تست هماهنگی با Tutor LMS
 */
function oa_tutor_integration_test_shortcode($atts) {
    if (!current_user_can('manage_options')) {
        return '<p>شما دسترسی لازم را ندارید.</p>';
    }
    
    ob_start();
    ?>
    <div class="oa-tutor-test-container">
        <h2>تست هماهنگی با Tutor LMS</h2>
        
        <div class="oa-test-section">
            <h3>بررسی وضعیت کلی</h3>
            <button type="button" class="oa-btn oa-btn-primary" id="test-integration">
                تست هماهنگی
            </button>
            <div id="integration-results"></div>
        </div>
        
        <div class="oa-test-section">
            <h3>بررسی وضعیت Tutor LMS</h3>
            <button type="button" class="oa-btn oa-btn-secondary" id="check-tutor-status">
                بررسی وضعیت
            </button>
            <div id="tutor-status-results"></div>
        </div>
        
        <div class="oa-test-section">
            <h3>تست نمایش آزمون</h3>
            <button type="button" class="oa-btn oa-btn-success" id="test-quiz-display">
                تست نمایش
            </button>
            <div id="quiz-display-results"></div>
        </div>
        
        <div class="oa-test-section">
            <h3>نمایش آزمون تست</h3>
            <?php echo do_shortcode('[oa_tutor_quiz]'); ?>
        </div>
    </div>
    
    <style>
    .oa-tutor-test-container {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 8px;
    }
    
    .oa-test-section {
        background: #ffffff;
        padding: 20px;
        margin: 20px 0;
        border-radius: 6px;
        border: 1px solid #e1e5e9;
    }
    
    .oa-test-section h3 {
        margin-top: 0;
        color: #2c3e50;
    }
    
    .oa-btn {
        background: #3498db;
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        margin: 5px;
    }
    
    .oa-btn:hover {
        background: #2980b9;
    }
    
    .oa-btn-secondary {
        background: #95a5a6;
    }
    
    .oa-btn-secondary:hover {
        background: #7f8c8d;
    }
    
    .oa-btn-success {
        background: #27ae60;
    }
    
    .oa-btn-success:hover {
        background: #229954;
    }
    
    .test-result {
        margin: 10px 0;
        padding: 10px;
        border-radius: 4px;
    }
    
    .test-result.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .test-result.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .test-result.info {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-integration').on('click', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oa_test_tutor_integration',
                    nonce: '<?php echo wp_create_nonce('oa_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="test-result success">';
                        html += '<h4>نتایج تست هماهنگی:</h4>';
                        html += '<ul>';
                        for (var key in response.data) {
                            var status = response.data[key] ? '✅' : '❌';
                            html += '<li>' + key + ': ' + status + '</li>';
                        }
                        html += '</ul>';
                        html += '</div>';
                        $('#integration-results').html(html);
                    } else {
                        $('#integration-results').html('<div class="test-result error">خطا: ' + response.data.message + '</div>');
                    }
                }
            });
        });
        
        $('#check-tutor-status').on('click', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oa_check_tutor_status',
                    nonce: '<?php echo wp_create_nonce('oa_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="test-result info">';
                        html += '<h4>وضعیت Tutor LMS:</h4>';
                        html += '<p>فعال: ' + (response.data.active ? '✅' : '❌') + '</p>';
                        if (response.data.active) {
                            html += '<p>نسخه: ' + response.data.version + '</p>';
                            html += '<p>تعداد درس‌ها: ' + response.data.courses_count + '</p>';
                        }
                        html += '</div>';
                        $('#tutor-status-results').html(html);
                    }
                }
            });
        });
        
        $('#test-quiz-display').on('click', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oa_test_quiz_display',
                    nonce: '<?php echo wp_create_nonce('oa_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="test-result success">';
                        html += '<h4>نتایج تست نمایش:</h4>';
                        html += '<p>شورت‌کد کار می‌کند: ' + (response.data.shortcode_works ? '✅' : '❌') + '</p>';
                        html += '<p>حاوی آزمون: ' + (response.data.contains_quiz ? '✅' : '❌') + '</p>';
                        html += '<p>حاوی فرم: ' + (response.data.contains_form ? '✅' : '❌') + '</p>';
                        html += '<p>طول خروجی: ' + response.data.output_length + ' کاراکتر</p>';
                        html += '</div>';
                        $('#quiz-display-results').html(html);
                    }
                }
            });
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
}

// ثبت شورت‌کد تست
add_shortcode('oa_tutor_test', 'oa_tutor_integration_test_shortcode');
