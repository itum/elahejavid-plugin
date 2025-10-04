<?php
/**
 * فایل تست برای افزونه تست تشخیص چاقی
 * این فایل برای بررسی عملکرد افزونه استفاده می‌شود
 */

// بررسی وجود افزونه
if (!class_exists('ObesityAssessment')) {
    die('افزونه تست تشخیص چاقی یافت نشد!');
}

echo "<h1>تست افزونه تست تشخیص چاقی</h1>";

// بررسی جداول دیتابیس
global $wpdb;

echo "<h2>بررسی جداول دیتابیس:</h2>";

$tables = array(
    'oa_groups' => 'گروه‌ها',
    'oa_questions' => 'سوالات', 
    'oa_options' => 'گزینه‌ها',
    'oa_results' => 'نتایج'
);

foreach ($tables as $table => $name) {
    $table_name = $wpdb->prefix . $table;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>✅ جدول $name: $count رکورد</p>";
}

// بررسی گروه‌ها
echo "<h2>گروه‌های موجود:</h2>";
$groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}oa_groups ORDER BY display_order");
foreach ($groups as $group) {
    echo "<p><strong>{$group->name}</strong> - ترتیب: {$group->display_order}</p>";
}

// بررسی سوالات
echo "<h2>تعداد سوالات هر گروه:</h2>";
$question_counts = $wpdb->get_results("
    SELECT g.name, COUNT(q.id) as question_count
    FROM {$wpdb->prefix}oa_groups g
    LEFT JOIN {$wpdb->prefix}oa_questions q ON g.id = q.group_id
    GROUP BY g.id
    ORDER BY g.display_order
");

foreach ($question_counts as $count) {
    echo "<p>{$count->name}: {$count->question_count} سوال</p>";
}

// بررسی گزینه‌ها
echo "<h2>تعداد کل گزینه‌ها:</h2>";
$total_options = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oa_options");
echo "<p>تعداد کل گزینه‌ها: $total_options</p>";

// تست شورت‌کد
echo "<h2>تست شورت‌کد:</h2>";
echo "<p>شورت‌کد [oa_quiz] باید فرم تست را نمایش دهد.</p>";

// بررسی فایل‌های CSS و JS
echo "<h2>بررسی فایل‌های استایل و اسکریپت:</h2>";
$plugin_url = plugin_dir_url(__FILE__);

$files = array(
    'assets/css/frontend.css' => 'استایل فرانت‌اند',
    'assets/css/admin.css' => 'استایل ادمین',
    'assets/js/frontend.js' => 'اسکریپت فرانت‌اند',
    'assets/js/admin.js' => 'اسکریپت ادمین'
);

foreach ($files as $file => $description) {
    $file_path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($file_path)) {
        echo "<p>✅ $description: موجود</p>";
    } else {
        echo "<p>❌ $description: یافت نشد</p>";
    }
}

echo "<h2>تست کامل شد!</h2>";
echo "<p>اگر همه موارد بالا ✅ هستند، افزونه آماده استفاده است.</p>";
echo "<p><strong>نکته:</strong> این فایل را بعد از تست حذف کنید.</p>";
?>
