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

// بررسی وجود سوالات
if (empty($all_questions)) {
    echo '<div class="oa-tutor-quiz-error" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; text-align: center;">
            <h3>خطا در بارگذاری آزمون</h3>
            <p style="color: #721c24;">متأسفانه سوالات آزمون یافت نشد. لطفاً با مدیر سایت تماس بگیرید.</p>
          </div>';
    return;
}
?>

<div class="oa-tutor-quiz-container" style="max-width: 800px; margin: 0 auto; padding: 20px; direction: rtl;">
    <div class="oa-tutor-quiz-header" style="text-align: center; margin-bottom: 30px;">
        <h2 style="color: #333; margin-bottom: 10px;">تست تشخیص نوع چاقی</h2>
        <p style="color: #666; font-size: 16px;">با پاسخ دادن به سوالات زیر، نوع چاقی خود را شناسایی کنید</p>
    </div>
    
    <div class="oa-tutor-progress" style="margin-bottom: 30px;">
        <div class="oa-tutor-progress-bar" style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
            <div class="oa-tutor-progress-fill" style="height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.3s ease; width: 0%;"></div>
        </div>
        <div class="oa-tutor-progress-text" style="text-align: center; margin-top: 10px; font-size: 14px; color: #666;">
            سوال <?php echo convertToPersianNumbers('1'); ?> از <?php echo convertToPersianNumbers(count($all_questions)); ?>
        </div>
    </div>
    
    <form class="oa-tutor-quiz-form" method="post" action="<?php echo home_url('/oa-result/'); ?>">
        <input type="hidden" name="oa_submit_tutor" value="1">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('oa_quiz_nonce'); ?>">
        
        <?php foreach ($all_questions as $index => $question): ?>
        <div class="oa-tutor-question" data-question-index="<?php echo $index; ?>" data-question-id="<?php echo $question->id; ?>" <?php echo $index > 0 ? 'style="display: none;"' : ''; ?>>
            <div class="oa-tutor-question-content" style="background: #f9f9f9; padding: 25px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="color: #333; margin-bottom: 20px; font-size: 18px;"><?php echo esc_html($question->question_text); ?></h3>
                
                <div class="oa-tutor-options">
                    <?php foreach ($question->options as $opt_index => $option): ?>
                    <div class="oa-tutor-option" data-option-index="<?php echo $opt_index; ?>" style="margin-bottom: 15px;">
                        <label style="display: flex; align-items: center; padding: 15px; background: white; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; transition: all 0.3s ease;">
                            <input type="radio" 
                                   name="answers[<?php echo $question->id; ?>]" 
                                   value="<?php echo $opt_index; ?>" 
                                   id="q<?php echo $question->id; ?>_o<?php echo $opt_index; ?>"
                                   style="margin-left: 12px;">
                            <span style="flex: 1; font-size: 16px;"><?php echo esc_html($option->option_text); ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="oa-tutor-navigation" style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button type="button" class="oa-tutor-btn oa-tutor-btn-secondary oa-tutor-btn-prev" style="display: none; padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    سوال قبلی
                </button>
                
                <button type="button" class="oa-tutor-btn oa-tutor-btn-primary oa-tutor-btn-next" disabled style="padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; margin-right: auto;">
                    سوال بعدی
                </button>
                
                <button type="submit" class="oa-tutor-btn oa-tutor-submit-btn" style="display: none; padding: 12px 24px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    ارسال تست
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </form>
</div>

<style>
.oa-tutor-quiz-container {
    font-family: 'Tahoma', 'Arial', sans-serif;
}

.oa-tutor-option label:hover {
    background: #f8f9fa !important;
    border-color: #667eea !important;
}

.oa-tutor-option input[type="radio"]:checked + span {
    color: #667eea;
    font-weight: bold;
}

.oa-tutor-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.oa-tutor-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentQuestion = 0;
    const totalQuestions = <?php echo count($all_questions); ?>;
    
    // مدیریت ناوبری
    $('.oa-tutor-btn-next').on('click', function() {
        if (currentQuestion < totalQuestions - 1) {
            showQuestion(currentQuestion + 1);
        }
    });
    
    $('.oa-tutor-btn-prev').on('click', function() {
        if (currentQuestion > 0) {
            showQuestion(currentQuestion - 1);
        }
    });
    
    // نمایش سوال
    function showQuestion(index) {
        $('.oa-tutor-question').hide();
        $('.oa-tutor-question[data-question-index="' + index + '"]').show();
        
        currentQuestion = index;
        updateProgress();
        updateNavigation();
    }
    
    // به‌روزرسانی نوار پیشرفت
    function updateProgress() {
        const progress = ((currentQuestion + 1) / totalQuestions) * 100;
        $('.oa-tutor-progress-fill').css('width', progress + '%');
        $('.oa-tutor-progress-text').text('سوال ' + convertToPersianNumbers(currentQuestion + 1) + ' از ' + convertToPersianNumbers(totalQuestions));
    }
    
    // به‌روزرسانی دکمه‌های ناوبری
    function updateNavigation() {
        $('.oa-tutor-btn-prev').toggle(currentQuestion > 0);
        $('.oa-tutor-btn-next').toggle(currentQuestion < totalQuestions - 1);
        $('.oa-tutor-submit-btn').toggle(currentQuestion === totalQuestions - 1);
        
        // فعال/غیرفعال کردن دکمه بعدی
        const hasAnswer = $('.oa-tutor-question[data-question-index="' + currentQuestion + '"] input[type="radio"]:checked').length > 0;
        $('.oa-tutor-btn-next').prop('disabled', !hasAnswer);
    }
    
    // فعال کردن دکمه بعدی هنگام انتخاب گزینه
    $('.oa-tutor-question input[type="radio"]').on('change', function() {
        updateNavigation();
    });
    
    // تابع تبدیل اعداد
    function convertToPersianNumbers(str) {
        const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str.toString().replace(/[0-9]/g, function(w) {
            return persian[english.indexOf(w)];
        });
    }
    
    // تنظیمات اولیه
    updateProgress();
    updateNavigation();
});
</script>
