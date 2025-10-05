<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// AJAX handlers برای پنل ادمین
add_action('wp_ajax_oa_get_groups', 'oa_get_groups');
add_action('wp_ajax_oa_save_group', 'oa_save_group');
add_action('wp_ajax_oa_delete_group', 'oa_delete_group');
add_action('wp_ajax_oa_get_group', 'oa_get_group');

add_action('wp_ajax_oa_get_questions', 'oa_get_questions');
add_action('wp_ajax_oa_save_question', 'oa_save_question');
add_action('wp_ajax_oa_delete_question', 'oa_delete_question');
add_action('wp_ajax_oa_get_question', 'oa_get_question');

add_action('wp_ajax_oa_get_results', 'oa_get_results');
add_action('wp_ajax_oa_view_result', 'oa_view_result');

add_action('wp_ajax_oa_get_settings', 'oa_get_settings');
add_action('wp_ajax_oa_save_settings', 'oa_save_settings');

// دریافت گروه‌ها
function oa_get_groups() {
    // بررسی دسترسی ادمین
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی - کاربر ادمین نیست'));
        return;
    }
    
    // بررسی nonce
    if (!wp_verify_nonce($_POST['nonce'], 'oa_admin_nonce')) {
        wp_send_json_error(array('message' => 'خطای امنیتی - nonce نامعتبر'));
        return;
    }
    
    global $wpdb;
    
    $groups = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}oa_groups 
        ORDER BY display_order
    ");
    
    wp_send_json_success($groups);
}

// ذخیره گروه
function oa_save_group() {
    // بررسی دسترسی ادمین
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی'));
        return;
    }
    
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $name = sanitize_text_field($_POST['name']);
    $description = sanitize_textarea_field($_POST['description']);
    $tips = sanitize_textarea_field($_POST['tips']);
    $video_url = esc_url_raw($_POST['video_url']);
    $display_order = intval($_POST['display_order']);
    
    $data = array(
        'name' => $name,
        'description' => $description,
        'tips' => $tips,
        'video_url' => $video_url,
        'display_order' => $display_order
    );
    
    if (isset($_POST['edit_id'])) {
        // ویرایش
        $id = intval($_POST['edit_id']);
        $result = $wpdb->update(
            $wpdb->prefix . 'oa_groups',
            $data,
            array('id' => $id)
        );
    } else {
        // افزودن جدید
        $result = $wpdb->insert(
            $wpdb->prefix . 'oa_groups',
            $data
        );
    }
    
    if ($result !== false) {
        wp_send_json_success(array('message' => 'گروه با موفقیت ذخیره شد'));
    } else {
        wp_send_json_error(array('message' => 'خطا در ذخیره گروه'));
    }
}

// حذف گروه
function oa_delete_group() {
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $id = intval($_POST['id']);
    
    // حذف سوالات مرتبط
    $questions = $wpdb->get_results($wpdb->prepare("
        SELECT id FROM {$wpdb->prefix}oa_questions 
        WHERE group_id = %d
    ", $id));
    
    foreach ($questions as $question) {
        // حذف گزینه‌ها
        $wpdb->delete($wpdb->prefix . 'oa_options', array('question_id' => $question->id));
    }
    
    // حذف سوالات
    $wpdb->delete($wpdb->prefix . 'oa_questions', array('group_id' => $id));
    
    // حذف گروه
    $result = $wpdb->delete($wpdb->prefix . 'oa_groups', array('id' => $id));
    
    if ($result !== false) {
        wp_send_json_success(array('message' => 'گروه با موفقیت حذف شد'));
    } else {
        wp_send_json_error(array('message' => 'خطا در حذف گروه'));
    }
}

// دریافت یک گروه
function oa_get_group() {
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $id = intval($_POST['id']);
    $group = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}oa_groups 
        WHERE id = %d
    ", $id));
    
    if ($group) {
        wp_send_json_success($group);
    } else {
        wp_send_json_error(array('message' => 'گروه یافت نشد'));
    }
}

// دریافت سوالات
function oa_get_questions() {
    // بررسی دسترسی ادمین
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی'));
        return;
    }
    
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $questions = $wpdb->get_results("
        SELECT q.*, g.name as group_name,
               COUNT(o.id) as options_count
        FROM {$wpdb->prefix}oa_questions q
        LEFT JOIN {$wpdb->prefix}oa_groups g ON q.group_id = g.id
        LEFT JOIN {$wpdb->prefix}oa_options o ON q.id = o.question_id
        GROUP BY q.id
        ORDER BY g.display_order, q.display_order
    ");
    
    wp_send_json_success($questions);
}

// ذخیره سوال
function oa_save_question() {
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $group_id = intval($_POST['group_id']);
    $question_text = sanitize_textarea_field($_POST['question_text']);
    $display_order = intval($_POST['display_order']);
    $options = $_POST['options'];
    
    $question_data = array(
        'group_id' => $group_id,
        'question_text' => $question_text,
        'display_order' => $display_order
    );
    
    if (isset($_POST['edit_id'])) {
        // ویرایش
        $id = intval($_POST['edit_id']);
        $wpdb->update(
            $wpdb->prefix . 'oa_questions',
            $question_data,
            array('id' => $id)
        );
        
        // حذف گزینه‌های قدیمی
        $wpdb->delete($wpdb->prefix . 'oa_options', array('question_id' => $id));
    } else {
        // افزودن جدید
        $wpdb->insert(
            $wpdb->prefix . 'oa_questions',
            $question_data
        );
        $id = $wpdb->insert_id;
    }
    
    // افزودن گزینه‌ها
    foreach ($options as $index => $option) {
        $wpdb->insert(
            $wpdb->prefix . 'oa_options',
            array(
                'question_id' => $id,
                'option_text' => sanitize_text_field($option['text']),
                'score' => intval($option['score']),
                'display_order' => $index + 1
            )
        );
    }
    
    wp_send_json_success(array('message' => 'سوال با موفقیت ذخیره شد'));
}

// حذف سوال
function oa_delete_question() {
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $id = intval($_POST['id']);
    
    // حذف گزینه‌ها
    $wpdb->delete($wpdb->prefix . 'oa_options', array('question_id' => $id));
    
    // حذف سوال
    $result = $wpdb->delete($wpdb->prefix . 'oa_questions', array('id' => $id));
    
    if ($result !== false) {
        wp_send_json_success(array('message' => 'سوال با موفقیت حذف شد'));
    } else {
        wp_send_json_error(array('message' => 'خطا در حذف سوال'));
    }
}

// دریافت یک سوال
function oa_get_question() {
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $id = intval($_POST['id']);
    $question = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}oa_questions 
        WHERE id = %d
    ", $id));
    
    if ($question) {
        $question->options = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}oa_options 
            WHERE question_id = %d 
            ORDER BY display_order
        ", $id));
        
        wp_send_json_success($question);
    } else {
        wp_send_json_error(array('message' => 'سوال یافت نشد'));
    }
}

// دریافت نتایج
function oa_get_results() {
    // بررسی دسترسی ادمین
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی'));
        return;
    }
    
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT r.*, 
               u.display_name as user_name,
               GROUP_CONCAT(g.name SEPARATOR ', ') as winning_groups,
               GROUP_CONCAT(
                   CONCAT(g.name, ': ', 
                   JSON_EXTRACT(r.group_scores, CONCAT('$.\"', g.id, '\"')))
                   SEPARATOR ' | '
               ) as group_scores
        FROM {$wpdb->prefix}oa_results r
        LEFT JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
        LEFT JOIN {$wpdb->prefix}oa_groups g ON FIND_IN_SET(g.id, REPLACE(REPLACE(r.winning_groups, '[', ''), ']', ''))
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT 100
    ");
    
    wp_send_json_success($results);
}

// مشاهده نتیجه
function oa_view_result() {
    $id = intval($_GET['id']);
    
    global $wpdb;
    $result = $wpdb->get_row($wpdb->prepare("
        SELECT r.*, u.display_name as user_name
        FROM {$wpdb->prefix}oa_results r
        LEFT JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
        WHERE r.id = %d
    ", $id));
    
    if ($result) {
        $group_scores = json_decode($result->group_scores, true);
        $winning_groups = json_decode($result->winning_groups, true);
        
        // دریافت نام گروه‌ها
        $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}oa_groups ORDER BY display_order");
        $group_names = array();
        foreach ($groups as $group) {
            $group_names[$group->id] = $group->name;
        }
        
        echo '<div style="direction: rtl; font-family: Tahoma; padding: 20px; max-width: 800px; margin: 0 auto;">';
        echo '<h2>جزئیات نتیجه تست</h2>';
        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<p><strong>تاریخ:</strong> ' . date('Y/m/d H:i', strtotime($result->created_at)) . '</p>';
        
        if ($result->user_id && $result->user_id > 0) {
            echo '<p><strong>کاربر:</strong> ' . ($result->user_name ?: 'کاربر ثبت‌شده') . '</p>';
        } else {
            echo '<p><strong>کاربر:</strong> مهمان (Session: ' . substr($result->session_id, 0, 8) . '...)</p>';
        }
        echo '</div>';
        
        echo '<h3>گروه‌های برنده:</h3>';
        echo '<ul>';
        foreach ($winning_groups as $group_id) {
            echo '<li>' . ($group_names[$group_id] ?? 'گروه ' . $group_id) . '</li>';
        }
        echo '</ul>';
        
        echo '<h3>امتیازات تفصیلی:</h3>';
        echo '<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">';
        echo '<tr style="background: #e9ecef;"><th style="padding: 10px; border: 1px solid #ddd;">گروه</th><th style="padding: 10px; border: 1px solid #ddd;">امتیاز</th><th style="padding: 10px; border: 1px solid #ddd;">وضعیت</th></tr>';
        
        foreach ($groups as $group) {
            $score = isset($group_scores[$group->id]) ? $group_scores[$group->id] : 0;
            $is_winner = in_array($group->id, $winning_groups);
            $status = $is_winner ? '🏆 برنده' : '❌';
            $row_style = $is_winner ? 'background: #d4edda; font-weight: bold;' : '';
            
            echo '<tr style="' . $row_style . '">';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . $group->name . '</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . $score . ' / 12</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . $status . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        $total_score = array_sum($group_scores);
        echo '<div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px;">';
        echo '<p><strong>امتیاز کل:</strong> ' . $total_score . ' / 108</p>';
        echo '</div>';
        
        echo '</div>';
    } else {
        echo '<div style="direction: rtl; font-family: Tahoma; padding: 20px; text-align: center;">';
        echo '<h2>نتیجه یافت نشد</h2>';
        echo '<p>نتیجه مورد نظر وجود ندارد یا حذف شده است.</p>';
        echo '</div>';
    }
    
    exit;
}

// دریافت تنظیمات
function oa_get_settings() {
    // بررسی دسترسی ادمین
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی'));
        return;
    }
    
    // بررسی nonce
    if (!wp_verify_nonce($_POST['nonce'], 'oa_admin_nonce')) {
        wp_send_json_error(array('message' => 'خطای امنیتی - nonce نامعتبر'));
        return;
    }
    
    // دریافت تنظیمات از options
    $settings = array(
        // تنظیمات متن‌های صفحه نتیجه
        'congratulations_title' => get_option('oa_congratulations_title', 'تبریک! 🎉'),
        'congratulations_text' => get_option('oa_congratulations_text', 'بر اساس تست شما، شما تیپ {GROUP_NAME} هستید. لطفاً ویدیو این چاقی را ببینید.'),
        'video_suggestion_text' => get_option('oa_video_suggestion_text', 'همچنین پیشنهاد می‌کنیم که همه ۹ ویدیو چاقی را هم ببینید تا اطلاعات کاملی در مورد انواع مختلف چاقی داشته باشید.'),
        'result_page_title' => get_option('oa_result_page_title', 'نتیجه تست تشخیص چاقی'),
        'result_page_subtitle' => get_option('oa_result_page_subtitle', 'بر اساس پاسخ‌های شما، نوع چاقی شما مشخص شد'),
        'video_title' => get_option('oa_video_title', 'ویدئوی آموزشی مربوط به دسته شما'),
        'tips_title' => get_option('oa_tips_title', 'توصیه‌های تخصصی:'),
        'score_breakdown_title' => get_option('oa_score_breakdown_title', 'جزئیات امتیازات شما:'),
        'total_score_text' => get_option('oa_total_score_text', 'امتیاز کل'),
        'multiple_types_text' => get_option('oa_multiple_types_text', 'شما عضو چند تیپ هستید'),
        'multiple_types_description' => get_option('oa_multiple_types_description', 'بر اساس پاسخ‌های شما، شما در دسته‌های زیر قرار می‌گیرید:'),
        
        // تنظیمات ورود و احراز هویت
        'enable_guest_access' => get_option('oa_enable_guest_access', '1'),
        'enable_digits_login' => get_option('oa_enable_digits_login', '0'),
        'digits_app_key' => get_option('oa_digits_app_key', ''),
        'digits_redirect_url' => get_option('oa_digits_redirect_url', ''),
        'digits_login_message' => get_option('oa_digits_login_message', 'برای شرکت در تست باید وارد شوید. لطفاً با شماره موبایل خود وارد شوید.'),
        
        // تنظیمات عمومی
        'test_title' => get_option('oa_test_title', 'تست تشخیص نوع چاقی'),
        'test_description' => get_option('oa_test_description', 'این تست به شما کمک می‌کند تا نوع چاقی خود را شناسایی کرده و راهکارهای مناسب را دریافت کنید.'),
        'home_button_text' => get_option('oa_home_button_text', '🏠 بازگشت به خانه'),
        'retake_test_text' => get_option('oa_retake_test_text', '🔄 تکرار تست'),
    );
    
    wp_send_json_success($settings);
}

// ذخیره تنظیمات
function oa_save_settings() {
    // بررسی دسترسی ادمین
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی'));
        return;
    }
    
    check_ajax_referer('oa_admin_nonce', 'nonce');
    
    // لیست فیلدهای مجاز
    $allowed_fields = array(
        'congratulations_title',
        'congratulations_text',
        'video_suggestion_text',
        'result_page_title',
        'result_page_subtitle',
        'video_title',
        'tips_title',
        'score_breakdown_title',
        'total_score_text',
        'multiple_types_text',
        'multiple_types_description',
        'enable_guest_access',
        'enable_digits_login',
        'digits_app_key',
        'digits_redirect_url',
        'digits_login_message',
        'test_title',
        'test_description',
        'home_button_text',
        'retake_test_text'
    );
    
    $saved_count = 0;
    $errors = array();
    
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            $value = $_POST[$field];
            
            // اعتبارسنجی بر اساس نوع فیلد
            switch ($field) {
                case 'enable_guest_access':
                case 'enable_digits_login':
                    $value = $value ? '1' : '0';
                    break;
                    
                case 'digits_redirect_url':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = 'آدرس بازگشت Digits نامعتبر است';
                        continue 2;
                    }
                    break;
                    
                default:
                    $value = sanitize_text_field($value);
                    break;
            }
            
            // ذخیره در options
            $option_name = 'oa_' . $field;
            if (update_option($option_name, $value)) {
                $saved_count++;
            }
        }
    }
    
    if (!empty($errors)) {
        wp_send_json_error(array('message' => implode('<br>', $errors)));
    } elseif ($saved_count > 0) {
        wp_send_json_success(array('message' => 'تنظیمات با موفقیت ذخیره شد'));
    } else {
        wp_send_json_error(array('message' => 'خطا در ذخیره تنظیمات'));
    }
}
