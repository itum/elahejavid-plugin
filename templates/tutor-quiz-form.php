<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// بررسی وجود نتایج قبلی کاربر
$user_id = get_current_user_id();
$session_id = session_id();
$has_existing_results = false;
$existing_results = null;

// اگر session وجود ندارد، شروع کن
if (empty($session_id)) {
    session_start();
    $session_id = session_id();
}

// بررسی نتایج از session
if (isset($_SESSION['oa_result'])) {
    $has_existing_results = true;
    $existing_results = $_SESSION['oa_result'];
} else {
    // بررسی نتایج از دیتابیس
    global $wpdb;
    $latest_result = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}oa_results 
        WHERE (user_id = %d OR session_id = %s) 
        ORDER BY created_at DESC 
        LIMIT 1
    ", $user_id, $session_id));
    
    if ($latest_result) {
        $has_existing_results = true;
        $existing_results = array(
            'group_scores' => json_decode($latest_result->group_scores, true),
            'winning_groups' => json_decode($latest_result->winning_groups, true),
            'created_at' => $latest_result->created_at
        );
    }
}

// تابع تبدیل اعداد انگلیسی به فارسی
function convertToPersianNumbers($str) {
    $english = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    return str_replace($english, $persian, $str);
}

// تابع تولید HTML نتایج
function generate_results_html($result_data) {
    global $wpdb;
    
    // دریافت اطلاعات گروه‌های برنده
    $winning_groups_info = array();
    foreach ($result_data['winning_groups'] as $group_id) {
        $group = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}oa_groups 
            WHERE id = %d
        ", $group_id));
        
        if ($group) {
            $winning_groups_info[] = array(
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'tips' => $group->tips,
                'video_url' => $group->video_url
            );
        }
    }
    
    // دریافت نام همه گروه‌ها برای نمایش امتیازات
    $all_groups = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}oa_groups ORDER BY display_order");
    $group_names = array();
    foreach ($all_groups as $group) {
        $group_names[$group->id] = $group->name;
    }
    
    $html = '';
    
    // متن تبریک
    $html .= '<div class="oa-congratulations-section">';
    $html .= '<h3 style="color: #28a745; font-size: 28px; margin-bottom: 15px;">تبریک! 🎉</h3>';
    
    if (!empty($winning_groups_info)) {
        if (count($winning_groups_info) === 1) {
            $html .= '<p style="font-size: 18px; margin-bottom: 20px;">بر اساس تست شما، شما تیپ <strong>' . esc_html($winning_groups_info[0]['name']) . '</strong> هستید.</p>';
        } else {
            $html .= '<p style="font-size: 18px; margin-bottom: 20px;">شما عضو چند تیپ هستید:</p>';
            foreach ($winning_groups_info as $group) {
                $html .= '<span class="oa-group-badge" style="display: inline-block; background: #28a745; color: white; padding: 5px 15px; margin: 5px; border-radius: 20px; font-size: 14px;">' . esc_html($group['name']) . '</span>';
            }
        }
    }
    $html .= '</div>';
    
    // اطلاعات گروه‌های برنده
    if (!empty($winning_groups_info)) {
        $html .= '<div class="oa-groups-info">';
        foreach ($winning_groups_info as $group) {
            $html .= '<div class="oa-group-detail" style="background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 10px; border-right: 4px solid #667eea;">';
            $html .= '<h4 style="color: #667eea; margin-bottom: 10px;">' . esc_html($group['name']) . '</h4>';
            $html .= '<p style="margin-bottom: 15px;">' . esc_html($group['description']) . '</p>';
            $html .= '<div style="background: #e3f2fd; padding: 15px; border-radius: 8px;">';
            $html .= '<strong>توصیه‌های تخصصی:</strong><br>';
            $html .= '<span style="font-size: 14px;">' . esc_html($group['tips']) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    // جزئیات امتیازات
    if (isset($result_data['group_scores'])) {
        $html .= '<div class="oa-scores-section" style="margin-top: 25px;">';
        $html .= '<h4 style="margin-bottom: 15px;">جزئیات امتیازات شما:</h4>';
        $html .= '<div style="background: #f8f9fa; padding: 15px; border-radius: 10px;">';
        
        foreach ($result_data['group_scores'] as $groupId => $score) {
            $groupName = isset($group_names[$groupId]) ? $group_names[$groupId] : 'گروه ' . $groupId;
            $isWinner = in_array($groupId, $result_data['winning_groups']);
            
            $html .= '<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd;">';
            $html .= '<span style="' . ($isWinner ? 'font-weight: bold; color: #28a745;' : '') . '">' . esc_html($groupName) . '</span>';
            $html .= '<span style="' . ($isWinner ? 'font-weight: bold; color: #28a745;' : '') . '">' . convertToPersianNumbers($score) . ' / ۱۲</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
    }
    
    // تاریخ انجام تست
    if (isset($result_data['created_at'])) {
        $html .= '<div class="oa-test-date" style="margin-top: 20px; text-align: center; color: #666; font-size: 14px;">';
        $html .= '<p>تاریخ انجام تست: ' . convertToPersianNumbers(date('Y/m/d H:i', strtotime($result_data['created_at']))) . '</p>';
        $html .= '</div>';
    }
    
    return $html;
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
    
    <div class="oa-tutor-progress" style="margin-bottom: 30px;" <?php echo $has_existing_results ? 'style="display: none;"' : ''; ?>>
        <div class="oa-tutor-progress-bar" style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
            <div class="oa-tutor-progress-fill" style="height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); transition: width 0.3s ease; width: 0%;"></div>
        </div>
        <div class="oa-tutor-progress-text" style="text-align: center; margin-top: 10px; font-size: 14px; color: #666;">
            سوال <?php echo convertToPersianNumbers('1'); ?> از <?php echo convertToPersianNumbers(count($all_questions)); ?>
        </div>
    </div>
    
    <form class="oa-tutor-quiz-form" method="post" action="" <?php echo $has_existing_results ? 'style="display: none;"' : ''; ?>>
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
    
    <!-- بخش نمایش نتایج -->
    <div class="oa-tutor-results" id="oa-tutor-results" <?php echo $has_existing_results ? '' : 'style="display: none;"'; ?>>
        <div class="oa-tutor-results-content">
            <h2 class="oa-tutor-results-title">نتیجه تست تشخیص نوع چاقی</h2>
            <?php if ($has_existing_results && isset($existing_results['created_at'])): ?>
                <div class="oa-results-info" style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-right: 4px solid #2196f3;">
                    <p style="margin: 0; color: #1976d2; font-size: 14px;">
                        <strong>📋 نتایج قبلی شما:</strong> این نتایج مربوط به تستی است که در تاریخ 
                        <?php echo convertToPersianNumbers(date('Y/m/d H:i', strtotime($existing_results['created_at']))); ?> 
                        انجام داده‌اید. برای انجام تست جدید، روی دکمه "تکرار تست" کلیک کنید.
                    </p>
                </div>
            <?php endif; ?>
            <?php if ($has_existing_results): ?>
                <div class="oa-tutor-results-body">
                    <?php echo generate_results_html($existing_results); ?>
                </div>
            <?php else: ?>
                <div class="oa-tutor-results-body">
                    <!-- محتوای نتایج اینجا نمایش داده می‌شود -->
                </div>
            <?php endif; ?>
            <div class="oa-tutor-results-actions">
                <button type="button" class="oa-tutor-btn oa-tutor-btn-primary oa-retake-quiz">
                    تکرار تست
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.oa-tutor-quiz-container {
    /* فونت از قالب اصلی سایت ارث‌بری می‌شود */
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

/* استایل‌های بخش نتایج */
.oa-tutor-results {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 30px;
    margin-top: 30px;
    color: white;
    text-align: center;
    animation: fadeInUp 0.8s ease-out;
}

.oa-tutor-results-content {
    background: rgba(255, 255, 255, 0.95);
    color: #333;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.oa-tutor-results-title {
    font-size: 24px;
    margin: 0 0 20px 0;
    color: #333;
    font-weight: bold;
}

.oa-tutor-results-body {
    margin-bottom: 25px;
}

.oa-tutor-results-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
    
    // مدیریت ارسال فرم
    $('.oa-tutor-quiz-form').on('submit', function(e) {
        e.preventDefault();
        submitTutorQuiz();
    });
    
    // دکمه تکرار تست
    $('.oa-retake-quiz').on('click', function() {
        // پاک کردن نتایج از session و دیتابیس
        clearExistingResults();
        resetQuiz();
    });
    
    // تابع ارسال آزمون
    function submitTutorQuiz() {
        // جمع‌آوری پاسخ‌ها
        const answers = {};
        $('.oa-tutor-question').each(function() {
            const questionId = $(this).data('question-id');
            const selectedOption = $(this).find('input[type="radio"]:checked');
            if (selectedOption.length > 0) {
                answers[questionId] = selectedOption.closest('.oa-tutor-option').data('option-index');
            }
        });
        
        // بررسی تکمیل همه سوالات
        if (Object.keys(answers).length < <?php echo count($all_questions); ?>) {
            alert('لطفاً به همه سوالات پاسخ دهید.');
            return;
        }
        
        // نمایش لودینگ
        $('.oa-tutor-submit-btn').html('در حال پردازش...').prop('disabled', true);
        
        // ارسال AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'oa_submit_tutor_quiz',
                nonce: '<?php echo wp_create_nonce('oa_quiz_nonce'); ?>',
                answers: answers
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    alert('خطا در پردازش تست: ' + (response.data.message || 'خطای نامشخص'));
                    $('.oa-tutor-submit-btn').html('ارسال تست').prop('disabled', false);
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.');
                $('.oa-tutor-submit-btn').html('ارسال تست').prop('disabled', false);
            }
        });
    }
    
    // تابع نمایش نتایج
    function displayResults(resultData) {
        // مخفی کردن فرم آزمون
        $('.oa-tutor-quiz-form').hide();
        $('.oa-tutor-progress').hide();
        
        // نمایش نتایج
        const resultsHtml = generateResultsHtml(resultData);
        $('.oa-tutor-results-body').html(resultsHtml);
        $('.oa-tutor-results').show();
        
        // اسکرول به نتایج
        $('html, body').animate({
            scrollTop: $('.oa-tutor-results').offset().top - 100
        }, 500);
    }
    
    // تابع تولید HTML نتایج
    function generateResultsHtml(resultData) {
        let html = '';
        
        // متن تبریک
        html += '<div class="oa-congratulations-section">';
        html += '<h3 style="color: #28a745; font-size: 28px; margin-bottom: 15px;">تبریک! 🎉</h3>';
        
        if (resultData.winning_groups && resultData.winning_groups.length > 0) {
            if (resultData.winning_groups.length === 1) {
                html += '<p style="font-size: 18px; margin-bottom: 20px;">بر اساس تست شما، شما تیپ <strong>' + resultData.winning_groups[0].name + '</strong> هستید.</p>';
            } else {
                html += '<p style="font-size: 18px; margin-bottom: 20px;">شما عضو چند تیپ هستید:</p>';
                resultData.winning_groups.forEach(function(group) {
                    html += '<span class="oa-group-badge" style="display: inline-block; background: #28a745; color: white; padding: 5px 15px; margin: 5px; border-radius: 20px; font-size: 14px;">' + group.name + '</span>';
                });
            }
        }
        html += '</div>';
        
        // اطلاعات گروه‌های برنده
        if (resultData.winning_groups && resultData.winning_groups.length > 0) {
            html += '<div class="oa-groups-info">';
            resultData.winning_groups.forEach(function(group) {
                html += '<div class="oa-group-detail" style="background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 10px; border-right: 4px solid #667eea;">';
                html += '<h4 style="color: #667eea; margin-bottom: 10px;">' + group.name + '</h4>';
                html += '<p style="margin-bottom: 15px;">' + group.description + '</p>';
                html += '<div style="background: #e3f2fd; padding: 15px; border-radius: 8px;">';
                html += '<strong>توصیه‌های تخصصی:</strong><br>';
                html += '<span style="font-size: 14px;">' + group.tips + '</span>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
        }
        
        // جزئیات امتیازات
        if (resultData.group_scores) {
            html += '<div class="oa-scores-section" style="margin-top: 25px;">';
            html += '<h4 style="margin-bottom: 15px;">جزئیات امتیازات شما:</h4>';
            html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 10px;">';
            
            Object.keys(resultData.group_scores).forEach(function(groupId) {
                const score = resultData.group_scores[groupId];
                const groupName = resultData.group_names[groupId] || 'گروه ' + groupId;
                const isWinner = resultData.winning_groups && resultData.winning_groups.some(function(g) { return g.id == groupId; });
                
                html += '<div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd;">';
                html += '<span style="' + (isWinner ? 'font-weight: bold; color: #28a745;' : '') + '">' + groupName + '</span>';
                html += '<span style="' + (isWinner ? 'font-weight: bold; color: #28a745;' : '') + '">' + convertToPersianNumbers(score) + ' / ۱۲</span>';
                html += '</div>';
            });
            
            html += '</div>';
            html += '</div>';
        }
        
        return html;
    }
    
    // تابع پاک کردن نتایج موجود
    function clearExistingResults() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'oa_clear_quiz_results',
                nonce: '<?php echo wp_create_nonce('oa_quiz_nonce'); ?>'
            },
            success: function(response) {
                console.log('نتایج پاک شدند');
            },
            error: function() {
                console.log('خطا در پاک کردن نتایج');
            }
        });
    }
    
    // تابع بازنشانی آزمون
    function resetQuiz() {
        // پاک کردن پاسخ‌ها
        $('.oa-tutor-question input[type="radio"]').prop('checked', false);
        
        // بازگشت به سوال اول
        currentQuestion = 0;
        showQuestion(0);
        
        // نمایش مجدد فرم
        $('.oa-tutor-results').hide();
        $('.oa-tutor-quiz-form').show();
        $('.oa-tutor-progress').show();
        
        // اسکرول به بالای آزمون
        $('html, body').animate({
            scrollTop: $('.oa-tutor-quiz-container').offset().top - 100
        }, 500);
    }
});
</script>
