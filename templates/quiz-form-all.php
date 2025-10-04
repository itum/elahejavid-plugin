<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('convertToPersianNumbers')) {
    function convertToPersianNumbers($str) {
        return str_replace(array('0','1','2','3','4','5','6','7','8','9'), 
                         array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'), $str);
    }
}

global $wpdb;

// کوئری ساده
$questions = $wpdb->get_results("
    SELECT q.id, q.question_text, g.name as group_name
    FROM {$wpdb->prefix}oa_questions q
    JOIN {$wpdb->prefix}oa_groups g ON q.group_id = g.id
    ORDER BY g.display_order, q.display_order
");

$options = $wpdb->get_results("
    SELECT question_id, option_text, display_order
    FROM {$wpdb->prefix}oa_options
    ORDER BY question_id, display_order
");

$options_by_question = array();
foreach ($options as $option) {
    $options_by_question[$option->question_id][] = $option->option_text;
}
?>

<div class="oa-quiz-all">
    <h2>تست تشخیص نوع چاقی - همه سوالات</h2>
    <p>تمام سوالات را در یک صفحه مشاهده کنید و پاسخ دهید</p>
    
    <form method="post" action="<?php echo home_url('/oa-result/'); ?>">
        <input type="hidden" name="oa_submit_all" value="1">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('oa_quiz_nonce'); ?>">
        <?php 
        $q_num = 1;
        foreach ($questions as $question): 
        ?>
        <div class="question">
            <h4>سوال <?php echo convertToPersianNumbers($q_num); ?>: <?php echo esc_html($question->question_text); ?></h4>
            <?php if (isset($options_by_question[$question->id])): ?>
                <?php foreach ($options_by_question[$question->id] as $opt_index => $option): ?>
                <label>
                    <input type="radio" name="answers[<?php echo $question->id; ?>]" value="<?php echo $opt_index; ?>" required>
                    <?php echo esc_html($option); ?>
                </label><br>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php $q_num++; endforeach; ?>
        
        <button type="submit">ارسال تست</button>
    </form>
</div>

<style>
.oa-quiz-all{max-width:800px;margin:0 auto;padding:20px;direction:rtl}
.question{margin:20px 0;padding:15px;background:#f9f9f9;border-radius:5px}
.question h4{margin:0 0 10px 0;color:#333}
.question label{display:block;margin:5px 0;cursor:pointer}
.question input{margin-left:8px}
button{background:#007cba;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-size:16px}
button:hover{background:#005a87}
</style>