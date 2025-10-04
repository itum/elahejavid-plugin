<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
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
            <div class="oa-score-item" style="<?php echo $is_winner ? 'background: #e8f5e8; font-weight: bold;' : ''; ?>">
                <span class="oa-score-name"><?php echo esc_html($group->name); ?></span>
                <span class="oa-score-value"><?php echo $score; ?> / 12</span>
            </div>
            <?php endforeach; ?>
            
            <div class="oa-score-item" style="border-top: 2px solid #667eea; margin-top: 15px; padding-top: 15px;">
                <span class="oa-score-name" style="font-size: 16px;">امتیاز کل</span>
                <span class="oa-score-value" style="font-size: 16px;"><?php echo $total_score; ?> / 108</span>
            </div>
        </div>
        
        <div class="oa-retake-btn">
            <a href="<?php echo home_url(); ?>" class="oa-btn oa-btn-primary oa-btn-large">
                تکرار تست
            </a>
        </div>
    </div>
</div>

<style>
/* استایل‌های اضافی برای صفحه نتیجه */
.oa-result-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    padding: 20px 0;
}

.oa-result-content {
    animation: fadeInUp 0.8s ease-out;
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

.oa-video-wrapper {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-radius: 12px;
    overflow: hidden;
}

.oa-group-info {
    animation: slideInRight 0.6s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.oa-score-item {
    transition: all 0.3s ease;
}

.oa-score-item:hover {
    background: #f8f9fa;
    transform: translateX(-5px);
}
</style>
