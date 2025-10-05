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

// دریافت نتیجه از session
$result = null;
if (isset($_SESSION['oa_result'])) {
    $result = $_SESSION['oa_result'];
} else {
    // دریافت آخرین نتیجه کاربر
    $user_id = get_current_user_id();
    $session_id = session_id();
    
    $latest_result = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}oa_results 
        WHERE (user_id = %d OR session_id = %s) 
        ORDER BY created_at DESC 
        LIMIT 1
    ", $user_id, $session_id));
    
    if ($latest_result) {
        $result = array(
            'group_scores' => json_decode($latest_result->group_scores, true),
            'winning_groups' => json_decode($latest_result->winning_groups, true)
        );
    }
}

if (!$result) {
    wp_redirect(home_url());
    exit;
}

// دریافت اطلاعات گروه‌های برنده
$winning_groups_info = array();
foreach ($result['winning_groups'] as $group_id) {
    $group = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}oa_groups 
        WHERE id = %d
    ", $group_id));
    
    if ($group) {
        $winning_groups_info[] = $group;
    }
}

// محاسبه امتیاز کل
$total_score = array_sum($result['group_scores']);
?>

<div class="oa-result-container">
    <div class="oa-result-header">
        <h1 class="oa-result-title">نتیجه تست تشخیص چاقی</h1>
        <p class="oa-result-subtitle">بر اساس پاسخ‌های شما، نوع چاقی شما مشخص شد</p>
        
        <!-- متن تبریک با فونت درشت -->
        <div class="oa-congratulations">
            <h2 class="oa-congratulations-title">تبریک! 🎉</h2>
            <p class="oa-congratulations-text">
                بر اساس تست شما، شما تیپ 
                <?php if (count($winning_groups_info) == 1): ?>
                    <strong><?php echo esc_html($winning_groups_info[0]->name); ?></strong>
                <?php else: ?>
                    <strong>چندگانه</strong>
                <?php endif; ?>
                هستید. لطفاً ویدیو این چاقی را ببینید.
            </p>
        </div>
        
        <!-- متن پیشنهاد تماشای ویدیو با فونت ریزتر -->
        <div class="oa-video-suggestion">
            <p class="oa-video-suggestion-text">
                همچنین پیشنهاد می‌کنیم که همه ۹ ویدیو چاقی را هم ببینید تا اطلاعات کاملی در مورد انواع مختلف چاقی داشته باشید.
            </p>
        </div>
    </div>
    
    <div class="oa-result-content">
        <?php if (count($winning_groups_info) == 1): ?>
            <div class="oa-group-info">
                <h2 class="oa-group-name"><?php echo esc_html($winning_groups_info[0]->name); ?></h2>
                <p class="oa-group-description"><?php echo esc_html($winning_groups_info[0]->description); ?></p>
                
                <div class="oa-group-tips">
                    <h4>توصیه‌های تخصصی:</h4>
                    <p><?php echo esc_html($winning_groups_info[0]->tips); ?></p>
                </div>
            </div>
            
            <?php if (!empty($winning_groups_info[0]->video_url)): ?>
            <div class="oa-video-container">
                <h3 class="oa-video-title">ویدئوی آموزشی مربوط به دسته شما</h3>
                <div class="oa-video-wrapper">
                    <video controls preload="metadata">
                        <source src="<?php echo esc_url($winning_groups_info[0]->video_url); ?>" type="video/mp4">
                        مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.
                    </video>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="oa-group-info">
                <h2 class="oa-group-name">شما عضو چند تیپ هستید</h2>
                <p class="oa-group-description">
                    بر اساس پاسخ‌های شما، شما در دسته‌های زیر قرار می‌گیرید:
                </p>
                
                <?php foreach ($winning_groups_info as $group): ?>
                <div class="oa-group-info" style="margin-bottom: 20px;">
                    <h3 class="oa-group-name"><?php echo esc_html($group->name); ?></h3>
                    <p class="oa-group-description"><?php echo esc_html($group->description); ?></p>
                    
                    <div class="oa-group-tips">
                        <h4>توصیه‌های تخصصی:</h4>
                        <p><?php echo esc_html($group->tips); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="oa-video-container">
                <h3 class="oa-video-title">ویدئوهای آموزشی مربوط به دسته‌های شما</h3>
                <?php foreach ($winning_groups_info as $index => $group): ?>
                    <?php if (!empty($group->video_url)): ?>
                    <div style="margin-bottom: 30px;">
                        <h4><?php echo esc_html($group->name); ?></h4>
                        <div class="oa-video-wrapper">
                            <video controls preload="metadata">
                                <source src="<?php echo esc_url($group->video_url); ?>" type="video/mp4">
                                مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.
                            </video>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="oa-score-breakdown">
            <h4>جزئیات امتیازات شما:</h4>
            <?php 
            $all_groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}oa_groups ORDER BY display_order");
            foreach ($all_groups as $group): 
                $score = isset($result['group_scores'][$group->id]) ? $result['group_scores'][$group->id] : 0;
                $is_winner = in_array($group->id, $result['winning_groups']);
            ?>
            <div class="oa-score-item <?php echo $is_winner ? 'winner' : ''; ?>">
                <span class="oa-score-name"><?php echo esc_html($group->name); ?></span>
                <span class="oa-score-value"><?php echo convertToPersianNumbers($score); ?> / <?php echo convertToPersianNumbers('12'); ?></span>
            </div>
            <?php endforeach; ?>
            
            <div class="oa-score-item" style="border-top: 3px solid #667eea; margin-top: 20px; padding-top: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); font-size: 18px; font-weight: bold;">
                <span class="oa-score-name">امتیاز کل</span>
                <span class="oa-score-value"><?php echo convertToPersianNumbers($total_score); ?> / <?php echo convertToPersianNumbers('108'); ?></span>
            </div>
        </div>
        
        <div class="oa-navigation-buttons">
            <a href="<?php echo home_url(); ?>" class="oa-btn oa-btn-secondary">
                🏠 بازگشت به خانه
            </a>
            <a href="<?php echo home_url('/تست-چاقی/'); ?>" class="oa-btn oa-btn-primary oa-btn-large">
                🔄 تکرار تست
            </a>
        </div>
    </div>
</div>

<style>
/* فونت ایران‌سنس */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

/* استایل‌های بهبود یافته برای صفحه نتیجه */
.oa-result-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 30px 15px;
    font-family: 'Inter', 'Tahoma', 'Arial', sans-serif;
    direction: rtl;
    text-align: right;
}

.oa-result-header {
    text-align: center;
    margin-bottom: 25px;
    padding: 25px 20px;
    background: rgba(255, 255, 255, 0.95);
    color: #333;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
}

.oa-result-title {
    font-size: 26px;
    margin: 0 0 10px 0;
    font-weight: 600;
    color: #333;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}

.oa-result-subtitle {
    font-size: 16px;
    margin: 0;
    color: #666;
    line-height: 1.5;
}

/* استایل‌های متن تبریک با فونت درشت */
.oa-congratulations {
    margin: 25px 0;
    padding: 20px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(5px);
}

.oa-congratulations-title {
    font-size: 32px;
    font-weight: bold;
    margin: 0 0 15px 0;
    color: #fff;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    text-align: center;
}

.oa-congratulations-text {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: #fff;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    text-align: center;
    line-height: 1.6;
}

.oa-congratulations-text strong {
    color: #ffd700;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.4);
}

/* استایل‌های متن پیشنهاد ویدیو با فونت ریزتر */
.oa-video-suggestion {
    margin: 20px 0 0 0;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.oa-video-suggestion-text {
    font-size: 14px;
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    text-align: center;
    line-height: 1.5;
    font-style: italic;
}

.oa-result-content {
    background: rgba(255, 255, 255, 0.95);
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    backdrop-filter: blur(10px);
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.oa-group-info {
    margin-bottom: 25px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    border-right: 4px solid #667eea;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    animation: slideInRight 0.6s ease-out;
}

.oa-group-name {
    font-size: 22px;
    font-weight: 600;
    color: #333;
    margin: 0 0 10px 0;
    text-align: center;
}

.oa-group-description {
    font-size: 16px;
    color: #666;
    margin: 0 0 15px 0;
    line-height: 1.6;
    text-align: center;
}

.oa-group-tips {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 25px;
    border-radius: 12px;
    border-right: 4px solid #2196f3;
    box-shadow: 0 5px 15px rgba(33, 150, 243, 0.2);
}

.oa-group-tips h4 {
    margin: 0 0 15px 0;
    color: #1976d2;
    font-size: 18px;
    font-weight: bold;
}

.oa-group-tips p {
    margin: 0;
    color: #333;
    line-height: 1.8;
    font-size: 16px;
}

/* بهینه‌سازی ویدیو - ریسپانسیو */
.oa-video-container {
    margin: 25px 0;
    text-align: center;
}

.oa-video-title {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 20px 0;
    color: #333;
    text-align: center;
}

.oa-video-wrapper {
    position: relative;
    width: 100%;
    max-width: 500px;
    margin: 0 auto;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    transition: transform 0.3s ease;
    aspect-ratio: 16/9; /* نسبت استاندارد ویدیو */
}

.oa-video-wrapper:hover {
    transform: scale(1.02);
}

.oa-video-wrapper video {
    width: 100%;
    height: 100%;
    object-fit: contain; /* نمایش کامل ویدیو */
    display: block;
    border-radius: 12px;
}

/* بهبود جزئیات امتیازات */
.oa-score-breakdown {
    margin-top: 25px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.oa-score-breakdown h4 {
    margin: 0 0 25px 0;
    color: #333;
    font-size: 22px;
    font-weight: bold;
    text-align: center;
}

.oa-score-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    margin-bottom: 10px;
    background: white;
    border-radius: 10px;
    border-right: 4px solid #e9ecef;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.oa-score-item:hover {
    background: #f8f9fa;
    transform: translateX(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.oa-score-item.winner {
    background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
    border-right-color: #28a745;
    font-weight: bold;
}

.oa-score-name {
    font-weight: 500;
    color: #333;
    font-size: 16px;
}

.oa-score-value {
    font-weight: bold;
    color: #667eea;
    font-size: 16px;
}

.oa-score-item.winner .oa-score-value {
    color: #28a745;
}

/* دکمه‌های ناوبری بهبود یافته و ریسپانسیو */
.oa-navigation-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 25px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    flex-wrap: wrap;
}

.oa-btn {
    padding: 15px 30px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    min-width: 150px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    flex: 1;
    max-width: 250px;
}

.oa-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.oa-btn-primary:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4c93 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.oa-btn-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
}

.oa-btn-secondary:hover {
    background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.oa-btn-large {
    padding: 18px 40px;
    font-size: 18px;
    min-width: 200px;
}

/* انیمیشن‌های اضافی */
.oa-group-info {
    animation: slideInRight 0.6s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(50px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.oa-video-container {
    animation: fadeInUp 0.8s ease-out 0.2s both;
}

.oa-score-breakdown {
    animation: fadeInUp 0.8s ease-out 0.4s both;
}

.oa-navigation-buttons {
    animation: fadeInUp 0.8s ease-out 0.6s both;
}

/* ریسپانسیو بهبود یافته */
@media (max-width: 1024px) {
    .oa-result-container {
        padding: 25px 15px;
    }
    
    .oa-video-wrapper {
        max-width: 450px;
    }
    
    .oa-navigation-buttons {
        flex-direction: row;
        gap: 20px;
    }
    
    .oa-btn {
        flex: 1;
        max-width: 200px;
    }
}

@media (max-width: 900px) {
    .oa-result-container {
        padding: 20px 12px;
    }
    
    .oa-result-content {
        padding: 20px 15px;
    }
    
    .oa-group-info {
        padding: 18px 15px;
    }
    
    .oa-video-wrapper {
        max-width: 100%;
    }
}

@media (max-width: 768px) {
    .oa-result-container {
        padding: 20px 10px;
    }
    
    .oa-result-header {
        padding: 25px 15px;
        margin-bottom: 25px;
    }
    
    .oa-result-title {
        font-size: 22px;
        line-height: 1.3;
    }
    
    .oa-result-subtitle {
        font-size: 15px;
        line-height: 1.4;
    }
    
    .oa-congratulations-title {
        font-size: 26px;
        margin-bottom: 12px;
    }
    
    .oa-congratulations-text {
        font-size: 18px;
        line-height: 1.5;
    }
    
    .oa-video-suggestion-text {
        font-size: 13px;
        line-height: 1.4;
    }
    
    .oa-result-content {
        padding: 20px 15px;
    }
    
    .oa-group-info {
        padding: 18px 15px;
        margin-bottom: 25px;
    }
    
    .oa-group-name {
        font-size: 20px;
        line-height: 1.3;
    }
    
    .oa-group-description {
        font-size: 15px;
        line-height: 1.5;
    }
    
    .oa-group-tips {
        padding: 20px 15px;
    }
    
    .oa-group-tips h4 {
        font-size: 16px;
        margin-bottom: 12px;
    }
    
    .oa-group-tips p {
        font-size: 14px;
        line-height: 1.6;
    }
    
    .oa-video-container {
        margin: 20px 0;
    }
    
    .oa-video-title {
        font-size: 18px;
        margin-bottom: 15px;
    }
    
    .oa-video-wrapper {
        max-width: 100%;
        aspect-ratio: 16/9; /* تغییر از مربعی به مستطیل */
        margin: 0 auto;
    }
    
    .oa-video-wrapper video {
        width: 100%;
        height: 100%;
        object-fit: contain; /* تغییر از cover به contain */
    }
    
    .oa-score-breakdown {
        padding: 18px 15px;
        margin-top: 20px;
    }
    
    .oa-score-breakdown h4 {
        font-size: 18px;
        margin-bottom: 20px;
    }
    
    .oa-score-item {
        padding: 12px 15px;
        margin-bottom: 8px;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .oa-score-name {
        font-size: 14px;
        flex: 1;
        margin-left: 10px;
    }
    
    .oa-score-value {
        font-size: 14px;
        font-weight: bold;
    }
    
    .oa-navigation-buttons {
        flex-direction: column;
        gap: 12px;
        padding: 20px 15px;
        margin-top: 20px;
    }
    
    .oa-btn {
        width: 100%;
        min-width: auto;
        padding: 14px 25px;
        font-size: 15px;
    }
    
    .oa-btn-large {
        padding: 16px 30px;
        font-size: 16px;
    }
}

@media (max-width: 480px) {
    .oa-result-container {
        padding: 15px 8px;
    }
    
    .oa-result-header {
        padding: 20px 12px;
        margin-bottom: 20px;
    }
    
    .oa-result-title {
        font-size: 18px;
        line-height: 1.2;
    }
    
    .oa-result-subtitle {
        font-size: 14px;
        line-height: 1.3;
    }
    
    .oa-congratulations-title {
        font-size: 22px;
        margin-bottom: 10px;
    }
    
    .oa-congratulations-text {
        font-size: 16px;
        line-height: 1.4;
    }
    
    .oa-video-suggestion-text {
        font-size: 12px;
        line-height: 1.3;
    }
    
    .oa-result-content {
        padding: 15px 12px;
    }
    
    .oa-group-info {
        padding: 15px 12px;
        margin-bottom: 20px;
    }
    
    .oa-group-name {
        font-size: 16px;
        line-height: 1.2;
    }
    
    .oa-group-description {
        font-size: 14px;
        line-height: 1.4;
    }
    
    .oa-group-tips {
        padding: 15px 12px;
    }
    
    .oa-group-tips h4 {
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .oa-group-tips p {
        font-size: 13px;
        line-height: 1.5;
    }
    
    .oa-video-title {
        font-size: 16px;
        margin-bottom: 12px;
    }
    
    .oa-video-wrapper {
        aspect-ratio: 4/3; /* نسبت مناسب‌تر برای موبایل */
    }
    
    .oa-score-breakdown {
        padding: 15px 12px;
    }
    
    .oa-score-breakdown h4 {
        font-size: 16px;
        margin-bottom: 15px;
    }
    
    .oa-score-item {
        padding: 10px 12px;
        margin-bottom: 6px;
        font-size: 13px;
    }
    
    .oa-score-name {
        font-size: 13px;
    }
    
    .oa-score-value {
        font-size: 13px;
    }
    
    .oa-navigation-buttons {
        padding: 15px 12px;
        gap: 10px;
    }
    
    .oa-btn {
        padding: 12px 20px;
        font-size: 14px;
    }
    
    .oa-btn-large {
        padding: 14px 25px;
        font-size: 15px;
    }
}

/* برای صفحات بسیار کوچک */
@media (max-width: 360px) {
    .oa-result-container {
        padding: 10px 5px;
    }
    
    .oa-result-header {
        padding: 15px 10px;
    }
    
    .oa-result-title {
        font-size: 16px;
    }
    
    .oa-result-subtitle {
        font-size: 13px;
    }
    
    .oa-congratulations-title {
        font-size: 20px;
        margin-bottom: 8px;
    }
    
    .oa-congratulations-text {
        font-size: 15px;
        line-height: 1.3;
    }
    
    .oa-video-suggestion-text {
        font-size: 11px;
        line-height: 1.2;
    }
    
    .oa-result-content {
        padding: 12px 8px;
    }
    
    .oa-group-info {
        padding: 12px 8px;
    }
    
    .oa-group-name {
        font-size: 15px;
    }
    
    .oa-group-description {
        font-size: 13px;
    }
    
    .oa-video-wrapper {
        aspect-ratio: 1/1; /* مربعی برای صفحات خیلی کوچک */
    }
    
    .oa-score-item {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    .oa-btn {
        padding: 10px 15px;
        font-size: 13px;
    }
}
</style>
