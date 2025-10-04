<?php
// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// آمار کلی
$total_groups = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oa_groups");
$total_questions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oa_questions");
$total_results = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oa_results");
$today_results = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oa_results WHERE DATE(created_at) = CURDATE()");

// آخرین نتایج
$recent_results = $wpdb->get_results("
    SELECT r.*, g.name as group_name
    FROM {$wpdb->prefix}oa_results r
    LEFT JOIN {$wpdb->prefix}oa_groups g ON FIND_IN_SET(g.id, REPLACE(REPLACE(r.winning_groups, '[', ''), ']', ''))
    ORDER BY r.created_at DESC
    LIMIT 10
");
?>

<div class="oa-admin-container">
    <div class="oa-admin-header">
        <h1>تست تشخیص نوع چاقی</h1>
        <p>مدیریت کامل سوالات، گروه‌ها و نتایج</p>
    </div>
    
    <div class="oa-admin-content">
        <div class="oa-admin-tabs">
            <button class="oa-admin-tab active" data-tab="dashboard">داشبورد</button>
            <button class="oa-admin-tab" data-tab="groups">گروه‌ها</button>
            <button class="oa-admin-tab" data-tab="questions">سوالات</button>
            <button class="oa-admin-tab" data-tab="results">نتایج</button>
            <button class="oa-admin-tab" data-tab="help">راهنما</button>
        </div>
        
        <!-- تب داشبورد -->
        <div class="oa-tab-content active" data-tab="dashboard">
            <div class="oa-stats-grid">
                <div class="oa-stat-card">
                    <div class="oa-stat-number"><?php echo $total_groups; ?></div>
                    <div class="oa-stat-label">گروه چاقی</div>
                </div>
                <div class="oa-stat-card">
                    <div class="oa-stat-number"><?php echo $total_questions; ?></div>
                    <div class="oa-stat-label">سوال</div>
                </div>
                <div class="oa-stat-card">
                    <div class="oa-stat-number"><?php echo $total_results; ?></div>
                    <div class="oa-stat-label">تست انجام شده</div>
                </div>
                <div class="oa-stat-card">
                    <div class="oa-stat-number"><?php echo $today_results; ?></div>
                    <div class="oa-stat-label">تست امروز</div>
                </div>
            </div>
            
            <h3>آخرین نتایج</h3>
            <table class="oa-table">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>کاربر</th>
                        <th>نتیجه</th>
                        <th>امتیاز کل</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_results as $result): ?>
                    <tr>
                        <td><?php echo date('Y/m/d H:i', strtotime($result->created_at)); ?></td>
                        <td><?php echo $result->user_id ? get_userdata($result->user_id)->display_name : 'مهمان'; ?></td>
                        <td><?php echo $result->group_name; ?></td>
                        <td><?php echo array_sum(json_decode($result->group_scores, true)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- تب گروه‌ها -->
        <div class="oa-tab-content" data-tab="groups">
            <h3>مدیریت گروه‌ها</h3>
            
            <form class="oa-form" id="group-form">
                <div class="oa-form-row">
                    <div class="oa-form-group">
                        <label for="group_name">نام گروه:</label>
                        <input type="text" id="group_name" name="name" required>
                    </div>
                    <div class="oa-form-group">
                        <label for="group_order">ترتیب نمایش:</label>
                        <input type="number" id="group_order" name="display_order" min="1" max="9">
                    </div>
                </div>
                
                <div class="oa-form-group">
                    <label for="group_description">توضیحات:</label>
                    <textarea id="group_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="oa-form-group">
                    <label for="group_tips">توصیه‌ها:</label>
                    <textarea id="group_tips" name="tips" rows="3"></textarea>
                </div>
                
                <div class="oa-form-group">
                    <label for="group_video">لینک ویدیو:</label>
                    <input type="url" id="group_video" name="video_url" placeholder="https://example.com/video.mp4">
                </div>
                
                <button type="submit" class="oa-btn oa-btn-primary">ذخیره گروه</button>
            </form>
            
            <div class="oa-table-container">
                <!-- جدول گروه‌ها توسط JavaScript بارگذاری می‌شود -->
            </div>
        </div>
        
        <!-- تب سوالات -->
        <div class="oa-tab-content" data-tab="questions">
            <h3>مدیریت سوالات</h3>
            
            <form class="oa-form" id="question-form">
                <div class="oa-form-row">
                    <div class="oa-form-group">
                        <label for="question_group">گروه:</label>
                        <select id="question_group" name="group_id" required>
                            <option value="">انتخاب گروه</option>
                            <?php
                            $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}oa_groups ORDER BY display_order");
                            foreach ($groups as $group): ?>
                            <option value="<?php echo $group->id; ?>"><?php echo esc_html($group->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="oa-form-group">
                        <label for="question_order">ترتیب نمایش:</label>
                        <input type="number" id="question_order" name="display_order" min="1" max="4">
                    </div>
                </div>
                
                <div class="oa-form-group">
                    <label for="question_text">متن سوال:</label>
                    <textarea id="question_text" name="question_text" rows="3" required></textarea>
                </div>
                
                <div class="oa-question-group">
                    <h4>گزینه‌ها:</h4>
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div class="oa-option-item">
                        <label>گزینه <?php echo $i; ?>:</label>
                        <input type="text" name="options[<?php echo $i-1; ?>][text]" placeholder="متن گزینه" required>
                        <input type="number" name="options[<?php echo $i-1; ?>][score]" class="oa-score-input" value="<?php echo $i-1; ?>" min="0" max="3" required>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <button type="submit" class="oa-btn oa-btn-primary">ذخیره سوال</button>
            </form>
            
            <div class="oa-table-container">
                <!-- جدول سوالات توسط JavaScript بارگذاری می‌شود -->
            </div>
        </div>
        
        <!-- تب نتایج -->
        <div class="oa-tab-content" data-tab="results">
            <h3>مدیریت نتایج</h3>
            
            <div class="oa-form-group">
                <input type="text" class="oa-search-input" placeholder="جستجو در نتایج...">
            </div>
            
            <div class="oa-table-container">
                <!-- جدول نتایج توسط JavaScript بارگذاری می‌شود -->
            </div>
        </div>
        
        <!-- تب راهنما -->
        <div class="oa-tab-content" data-tab="help">
            <h3>راهنمای استفاده از شورت کدها</h3>
            
            <div class="oa-help-section">
                <h4>🎯 شورت کد اصلی</h4>
                <div class="oa-code-block">
                    <code>[obesity_assessment]</code>
                    <button class="oa-btn oa-btn-small oa-btn-secondary oa-copy-btn" data-copy="[obesity_assessment]">کپی</button>
                </div>
                <p>این شورت کد فرم تست تشخیص نوع چاقی را نمایش می‌دهد. کاربران می‌توانند با پاسخ دادن به سوالات، نوع چاقی خود را تشخیص دهند.</p>
            </div>
            
            <div class="oa-help-section">
                <h4>📊 شورت کد نتایج</h4>
                <div class="oa-code-block">
                    <code>[obesity_results]</code>
                    <button class="oa-btn oa-btn-small oa-btn-secondary oa-copy-btn" data-copy="[obesity_results]">کپی</button>
                </div>
                <p>این شورت کد آمار و نتایج تست‌ها را نمایش می‌دهد. شامل تعداد کل تست‌ها، تست‌های امروز و آمار کلی است.</p>
            </div>
            
            <div class="oa-help-section">
                <h4>⚙️ پارامترهای شورت کد اصلی</h4>
                <p>شورت کد اصلی از پارامترهای زیر پشتیبانی می‌کند:</p>
                
                <div class="oa-params-grid">
                    <div class="oa-param-item">
                        <strong>show_title</strong>
                        <span class="oa-param-type">boolean</span>
                        <span class="oa-param-default">true</span>
                        <p>نمایش عنوان تست (true/false)</p>
                    </div>
                    
                    <div class="oa-param-item">
                        <strong>show_progress</strong>
                        <span class="oa-param-type">boolean</span>
                        <span class="oa-param-default">true</span>
                        <p>نمایش نوار پیشرفت (true/false)</p>
                    </div>
                    
                    <div class="oa-param-item">
                        <strong>button_text</strong>
                        <span class="oa-param-type">string</span>
                        <span class="oa-param-default">شروع تست</span>
                        <p>متن دکمه شروع تست</p>
                    </div>
                    
                    <div class="oa-param-item">
                        <strong>theme</strong>
                        <span class="oa-param-type">string</span>
                        <span class="oa-param-default">default</span>
                        <p>تم نمایش (default, modern, classic)</p>
                    </div>
                </div>
            </div>
            
            <div class="oa-help-section">
                <h4>📝 مثال‌های استفاده</h4>
                
                <div class="oa-example-block">
                    <h5>استفاده ساده:</h5>
                    <div class="oa-code-block">
                        <code>[obesity_assessment]</code>
                        <button class="oa-btn oa-btn-small oa-btn-secondary oa-copy-btn" data-copy="[obesity_assessment]">کپی</button>
                    </div>
                </div>
                
                <div class="oa-example-block">
                    <h5>بدون عنوان:</h5>
                    <div class="oa-code-block">
                        <code>[obesity_assessment show_title="false"]</code>
                        <button class="oa-btn oa-btn-small oa-btn-secondary oa-copy-btn" data-copy='[obesity_assessment show_title="false"]'>کپی</button>
                    </div>
                </div>
                
                <div class="oa-example-block">
                    <h5>با تم مدرن:</h5>
                    <div class="oa-code-block">
                        <code>[obesity_assessment theme="modern" button_text="شروع تست جدید"]</code>
                        <button class="oa-btn oa-btn-small oa-btn-secondary oa-copy-btn" data-copy='[obesity_assessment theme="modern" button_text="شروع تست جدید"]'>کپی</button>
                    </div>
                </div>
                
                <div class="oa-example-block">
                    <h5>بدون نوار پیشرفت:</h5>
                    <div class="oa-code-block">
                        <code>[obesity_assessment show_progress="false"]</code>
                        <button class="oa-btn oa-btn-small oa-btn-secondary oa-copy-btn" data-copy='[obesity_assessment show_progress="false"]'>کپی</button>
                    </div>
                </div>
            </div>
            
            <div class="oa-help-section">
                <h4>🔧 نحوه قرار دادن در صفحه یا پست</h4>
                <ol>
                    <li>در ویرایشگر پست یا صفحه، به جایی که می‌خواهید تست را نمایش دهید بروید</li>
                    <li>شورت کد مورد نظر را تایپ کنید یا از مثال‌های بالا کپی کنید</li>
                    <li>پست یا صفحه را منتشر کنید</li>
                    <li>تست در صفحه نمایش داده خواهد شد</li>
                </ol>
            </div>
            
            <div class="oa-help-section">
                <h4>💡 نکات مهم</h4>
                <ul>
                    <li>✅ مطمئن شوید که حداقل یک گروه و چند سوال ایجاد کرده‌اید</li>
                    <li>✅ هر گروه باید حداقل 4 سوال داشته باشد</li>
                    <li>✅ هر سوال باید 4 گزینه داشته باشد</li>
                    <li>✅ امتیاز گزینه‌ها باید از 0 تا 3 باشد</li>
                    <li>⚠️ تست فقط برای کاربران لاگین شده کار می‌کند (در صورت نیاز می‌توانید این تنظیم را تغییر دهید)</li>
                </ul>
            </div>
            
            <div class="oa-help-section">
                <h4>🆘 پشتیبانی</h4>
                <p>در صورت بروز مشکل یا نیاز به راهنمایی بیشتر، با تیم پشتیبانی تماس بگیرید.</p>
                <div class="oa-contact-info">
                    <p><strong>ایمیل:</strong> support@example.com</p>
                    <p><strong>تلفن:</strong> 021-12345678</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال ویرایش -->
<div class="oa-modal" id="edit-modal">
    <div class="oa-modal-content">
        <div class="oa-modal-header">
            <h3>ویرایش</h3>
            <button class="oa-modal-close">&times;</button>
        </div>
        <form class="oa-form" id="edit-form">
            <!-- محتویات توسط JavaScript پر می‌شود -->
        </form>
    </div>
</div>

<!-- مودال تایید حذف -->
<div class="oa-modal" id="delete-modal">
    <div class="oa-modal-content" style="max-width: 500px;">
        <div class="oa-modal-header">
            <h3>⚠️ تایید حذف</h3>
            <button class="oa-modal-close">&times;</button>
        </div>
        <div class="oa-delete-content">
            <div class="oa-delete-icon">🗑️</div>
            <div class="oa-delete-message">
                <p><strong>آیا مطمئن هستید؟</strong></p>
                <p id="delete-item-info"></p>
                <p class="oa-warning-text">این عمل قابل بازگردانی نیست!</p>
            </div>
            <div class="oa-delete-actions">
                <button type="button" class="oa-btn oa-btn-secondary" id="cancel-delete">انصراف</button>
                <button type="button" class="oa-btn oa-btn-danger" id="confirm-delete">حذف کن</button>
            </div>
        </div>
    </div>
</div>
