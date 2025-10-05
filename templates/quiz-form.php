<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تابع تبدیل اعداد انگلیسی به فارسی
function convertToPersianNumbers($str) {
    $english = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    return str_replace($english, $persian, $str);
}

global $wpdb;

// دریافت گروه‌ها و سوالات
$groups = $wpdb->get_results("
    SELECT g.*, 
           GROUP_CONCAT(q.id ORDER BY q.display_order) as question_ids
    FROM {$wpdb->prefix}oa_groups g
    LEFT JOIN {$wpdb->prefix}oa_questions q ON g.id = q.group_id
    GROUP BY g.id
    ORDER BY g.display_order
");

$all_questions = array();
foreach ($groups as $group) {
    if ($group->question_ids) {
        $question_ids = explode(',', $group->question_ids);
        foreach ($question_ids as $question_id) {
            $question = $wpdb->get_row($wpdb->prepare("
                SELECT q.*, 
                       GROUP_CONCAT(o.id ORDER BY o.display_order) as option_ids
                FROM {$wpdb->prefix}oa_questions q
                LEFT JOIN {$wpdb->prefix}oa_options o ON q.id = o.question_id
                WHERE q.id = %d
                GROUP BY q.id
            ", $question_id));
            
            if ($question) {
                $question->options = array();
                if ($question->option_ids) {
                    $option_ids = explode(',', $question->option_ids);
                    foreach ($option_ids as $option_id) {
                        $option = $wpdb->get_row($wpdb->prepare("
                            SELECT * FROM {$wpdb->prefix}oa_options 
                            WHERE id = %d
                        ", $option_id));
                        if ($option) {
                            $question->options[] = $option;
                        }
                    }
                }
                $all_questions[] = $question;
            }
        }
    }
}
?>

<div class="oa-quiz-container">
    <div class="oa-quiz-header">
        <h2>تست تشخیص نوع چاقی</h2>
        <p>با پاسخ دادن به سوالات زیر، نوع چاقی خود را شناسایی کنید</p>
    </div>
    
    <div class="oa-progress">
        <div class="oa-progress-bar">
            <div class="oa-progress-fill"></div>
        </div>
        <div class="oa-progress-text">سوال <?php echo convertToPersianNumbers('1'); ?> از <?php echo convertToPersianNumbers(count($all_questions)); ?></div>
    </div>
    
    <form class="oa-quiz-form" method="post">
        <?php foreach ($all_questions as $index => $question): ?>
        <div class="oa-question" data-question-index="<?php echo $index; ?>" data-question-id="<?php echo $question->id; ?>" <?php echo $index > 0 ? 'style="display: none;"' : ''; ?>>
            <h3><?php echo esc_html($question->question_text); ?></h3>
            
            <ul class="oa-options">
                <?php foreach ($question->options as $opt_index => $option): ?>
                <li class="oa-option" data-option-index="<?php echo $opt_index; ?>">
                    <input type="radio" 
                           name="answers[<?php echo $index; ?>]" 
                           value="<?php echo $opt_index; ?>" 
                           id="q<?php echo $index; ?>_o<?php echo $opt_index; ?>">
                    <label for="q<?php echo $index; ?>_o<?php echo $opt_index; ?>">
                        <?php echo esc_html($option->option_text); ?>
                    </label>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="oa-navigation">
                <button type="button" class="oa-btn oa-btn-secondary oa-btn-prev" style="display: none;">
                    سوال قبلی
                </button>
                
                <button type="button" class="oa-btn oa-btn-primary oa-btn-next" disabled>
                    سوال بعدی
                </button>
                
                <button type="submit" class="oa-btn oa-submit-btn" style="display: none;">
                    ارسال تست
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
</div>

<script>
// تنظیمات JavaScript برای فرم
window.oaQuizConfig = {
    totalQuestions: <?php echo count($all_questions); ?>,
    questions: <?php echo json_encode($all_questions); ?>
};
</script>
