jQuery(document).ready(function($) {
    'use strict';
    
    // متغیرهای عمومی
    let currentTab = 'dashboard';
    let isSubmitting = false;
    
    // متغیرهای حذف
    let deleteItem = null;
    
    // جلوگیری از کلیک‌های مکرر
    let isModalOpening = false;
    
    // مقداردهی اولیه
    function init() {
        // تب‌ها - استفاده از delegated event
        $(document).on('click', '.oa-admin-tab', function() {
            const tabId = $(this).data('tab');
            console.log('Tab clicked:', tabId);
            switchTab(tabId);
        });
        
        // فرم‌ها
        $(document).on('submit', '.oa-form', function(e) {
            e.preventDefault();
            handleFormSubmit($(this));
        });
        
        // مدیریت checkbox Digits
        $(document).on('change', '#enable_digits_login', function() {
            toggleDigitsSettings();
        });
        
        // مدیریت انتخاب ویدیو
        $(document).on('click', '#select-video-btn', function() {
            openMediaLibrary();
        });
        
        // مدیریت انتخاب ویدیو در مودال ویرایش
        $(document).on('click', '#edit-select-video-btn', function() {
            openEditMediaLibrary();
        });
        
        // مدیریت تغییر لینک ویدیو
        $(document).on('input', '#group_video', function() {
            updateVideoPreview();
        });
        
        // مدیریت تغییر لینک ویدیو در مودال ویرایش
        $(document).on('input', '#edit_group_video', function() {
            updateEditVideoPreview();
        });
        
        // دکمه‌های حذف و ویرایش (delegated events)
        $(document).on('click', '.oa-btn-delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = $(this).data('id');
            const type = $(this).data('type');
            const name = $(this).data('name');
            console.log('Delete button clicked:', id, type, name);
            confirmDelete(id, type, name);
        });
        
        $(document).on('click', '.oa-btn-edit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const id = $(this).data('id');
            const type = $(this).data('type');
            console.log('Edit button clicked:', id, type);
            openEditModal(id, type);
        });
        
        // بستن مودال
        $(document).on('click', '.oa-modal-close', function() {
            closeModal();
        });
        
        $(document).on('click', '.oa-modal', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // دکمه‌های مودال حذف
        $(document).on('click', '#cancel-delete', function() {
            closeDeleteModal();
        });
        
        $(document).on('click', '#confirm-delete', function() {
            executeDelete();
        });
        
        // تست AJAX
        testAjax();
        
        // شروع با تب داشبورد
        switchTab('dashboard');
    }
    
    // تست AJAX
    function testAjax() {
        console.log('Testing AJAX...', oa_admin_ajax);
        
        if (!oa_admin_ajax) {
            console.error('oa_admin_ajax is not defined!');
            showAlert('danger', 'خطا: متغیر AJAX تعریف نشده است');
            return;
        }
        
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_test'
            },
            success: function(response) {
                console.log('AJAX test successful:', response);
                if (response.success) {
                    console.log('User ID:', response.data.user_id);
                    console.log('Is Admin:', response.data.is_admin);
                    console.log('New Nonce:', response.data.nonce);
                    
                    // به‌روزرسانی nonce
                    if (response.data.nonce) {
                        oa_admin_ajax.nonce = response.data.nonce;
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX test failed:', xhr, status, error);
                showAlert('danger', 'خطا در تست AJAX: ' + error);
            }
        });
    }
    
    // تغییر تب
    function switchTab(tabId) {
        console.log('Switching to tab:', tabId);
        
        // حذف کلاس active از همه تب‌ها
        $('.oa-admin-tab').removeClass('active');
        // اضافه کردن کلاس active به تب انتخاب شده
        $('.oa-admin-tab[data-tab="' + tabId + '"]').addClass('active');
        
        // حذف کلاس active از همه محتواها
        $('.oa-tab-content').removeClass('active');
        // اضافه کردن کلاس active به محتوای تب انتخاب شده
        $('.oa-tab-content[data-tab="' + tabId + '"]').addClass('active');
        
        currentTab = tabId;
        console.log('Current tab set to:', currentTab);
        
        // بارگذاری داده‌های تب
        loadTabData(tabId);
    }
    
    // بارگذاری داده‌های تب
    function loadTabData(tabId) {
        switch(tabId) {
            case 'groups':
                loadGroups();
                break;
            case 'questions':
                loadQuestions();
                break;
            case 'results':
                loadResults();
                break;
            case 'settings':
                loadSettings();
                break;
            case 'help':
                // تب راهنما نیازی به بارگذاری داده ندارد
                break;
        }
    }
    
    // بارگذاری گروه‌ها
    function loadGroups() {
        console.log('Loading groups...', oa_admin_ajax);
        
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_get_groups',
                nonce: oa_admin_ajax.nonce
            },
            success: function(response) {
                console.log('Groups response:', response);
                if (response.success) {
                    renderGroupsTable(response.data);
                } else {
                    console.error('Groups error:', response.data);
                    showAlert('danger', 'خطا در بارگذاری گروه‌ها: ' + (response.data.message || 'نامشخص'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                showAlert('danger', 'خطا در ارتباط با سرور: ' + error);
            }
        });
    }
    
    // بارگذاری سوالات
    function loadQuestions() {
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_get_questions',
                nonce: oa_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderQuestionsTable(response.data);
                }
            }
        });
    }
    
    // بارگذاری نتایج
    function loadResults() {
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_get_results',
                nonce: oa_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderResultsTable(response.data);
                }
            }
        });
    }
    
    // بارگذاری تنظیمات
    function loadSettings() {
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_get_settings',
                nonce: oa_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateSettingsForm(response.data);
                } else {
                    console.error('Settings error:', response.data);
                    showAlert('danger', 'خطا در بارگذاری تنظیمات: ' + (response.data.message || 'نامشخص'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading settings:', xhr, status, error);
                showAlert('danger', 'خطا در ارتباط با سرور: ' + error);
            }
        });
    }
    
    // پر کردن فرم تنظیمات
    function populateSettingsForm(settings) {
        console.log('Populating settings form with data:', settings);
        
        // پر کردن فیلدهای فرم تنظیمات
        Object.keys(settings).forEach(function(key) {
            const $field = $('#settings-form [name="' + key + '"]');
            if ($field.length) {
                if ($field.is(':checkbox')) {
                    $field.prop('checked', settings[key] === '1' || settings[key] === 1 || settings[key] === true);
                } else {
                    $field.val(settings[key]);
                }
            }
        });
        
        // تنظیم نمایش/مخفی کردن تنظیمات Digits
        toggleDigitsSettings();
    }
    
    // نمایش/مخفی کردن تنظیمات Digits
    function toggleDigitsSettings() {
        const $digitsCheckbox = $('#enable_digits_login');
        const $digitsSettings = $('.digits-settings');
        
        if ($digitsCheckbox.is(':checked')) {
            $digitsSettings.addClass('show');
        } else {
            $digitsSettings.removeClass('show');
        }
    }
    
    // رندر جدول گروه‌ها
    function renderGroupsTable(groups) {
        let html = '<table class="oa-table">';
        html += '<thead><tr><th>نام گروه</th><th>توضیحات</th><th>ویدیو</th><th>ترتیب</th><th>عملیات</th></tr></thead>';
        html += '<tbody>';
        
        groups.forEach(function(group) {
            html += '<tr>';
            html += '<td>' + group.name + '</td>';
            html += '<td>' + (group.description || '') + '</td>';
            html += '<td>' + (group.video_url ? '✓' : '✗') + '</td>';
            html += '<td>' + group.display_order + '</td>';
            html += '<td class="oa-actions">';
            html += '<button class="oa-btn oa-btn-warning oa-btn-small oa-btn-edit" data-id="' + group.id + '" data-type="group">ویرایش</button>';
            html += '<button class="oa-btn oa-btn-danger oa-btn-small oa-btn-delete" data-id="' + group.id + '" data-type="group" data-name="' + escapeHtml(group.name) + '">حذف</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('.oa-tab-content[data-tab="groups"] .oa-table-container').html(html);
    }
    
    // رندر جدول سوالات
    function renderQuestionsTable(questions) {
        let html = '<table class="oa-table">';
        html += '<thead><tr><th>گروه</th><th>سوال</th><th>گزینه‌ها</th><th>ترتیب</th><th>عملیات</th></tr></thead>';
        html += '<tbody>';
        
        questions.forEach(function(question) {
            html += '<tr>';
            html += '<td>' + question.group_name + '</td>';
            html += '<td>' + question.question_text.substring(0, 50) + '...</td>';
            html += '<td>' + question.options_count + '</td>';
            html += '<td>' + question.display_order + '</td>';
            html += '<td class="oa-actions">';
            html += '<button class="oa-btn oa-btn-warning oa-btn-small oa-btn-edit" data-id="' + question.id + '" data-type="question">ویرایش</button>';
            html += '<button class="oa-btn oa-btn-danger oa-btn-small oa-btn-delete" data-id="' + question.id + '" data-type="question" data-name="' + escapeHtml(question.question_text.substring(0, 30) + '...') + '">حذف</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('.oa-tab-content[data-tab="questions"] .oa-table-container').html(html);
    }
    
    // رندر جدول نتایج
    function renderResultsTable(results) {
        let html = '<table class="oa-table">';
        html += '<thead><tr><th>تاریخ</th><th>کاربر</th><th>شماره تماس</th><th>گروه‌های برنده</th><th>امتیازات</th><th>عملیات</th></tr></thead>';
        html += '<tbody>';
        
        results.forEach(function(result) {
            // فرمت تاریخ
            const date = new Date(result.created_at);
            const formattedDate = date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR', {hour: '2-digit', minute: '2-digit'});
            
            // تشخیص نوع کاربر
            let userDisplay = 'مهمان';
            if (result.user_id && result.user_id > 0) {
                if (result.user_name) {
                    userDisplay = result.user_name;
                } else if (result.user_phone) {
                    userDisplay = result.user_phone;
                } else {
                    userDisplay = 'کاربر ثبت‌شده';
                }
            } else if (result.session_id) {
                userDisplay = 'کاربر موقت (Session)';
            }
            
            // شماره تماس
            let phoneDisplay = '-';
            if (result.user_phone) {
                phoneDisplay = result.user_phone;
            }
            
            html += '<tr>';
            html += '<td>' + formattedDate + '</td>';
            html += '<td>' + userDisplay + '</td>';
            html += '<td>' + phoneDisplay + '</td>';
            html += '<td>' + (result.winning_groups || 'نامشخص') + '</td>';
            html += '<td>' + (result.group_scores || 'نامشخص') + '</td>';
            html += '<td class="oa-actions">';
            html += '<button class="oa-btn oa-btn-primary oa-btn-small" onclick="viewResult(' + result.id + ')">مشاهده</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('.oa-tab-content[data-tab="results"] .oa-table-container').html(html);
    }
    
    // پردازش فرم
    function handleFormSubmit($form) {
        if (isSubmitting) return;
        
        isSubmitting = true;
        const $submitBtn = $form.find('.oa-btn-primary');
        const originalText = $submitBtn.text();
        
        $submitBtn.text('در حال ذخیره...').prop('disabled', true);
        
        const formData = new FormData($form[0]);
        
        // تشخیص نوع فرم
        let actionName;
        if ($form.attr('id') === 'settings-form') {
            actionName = 'oa_save_settings';
        } else if ($form.attr('id') === 'edit-form') {
            const editType = $form.data('edit-type');
            const editId = $form.data('edit-id');
            actionName = 'oa_save_' + editType;
            formData.append('edit_id', editId);
        } else {
            actionName = 'oa_save_' + currentTab.slice(0, -1);
        }
        
        formData.append('action', actionName);
        formData.append('nonce', oa_admin_ajax.nonce);
        
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showAlert('success', 'اطلاعات با موفقیت ذخیره شد.');
                    $form[0].reset();
                    loadTabData(currentTab);
                    
                    // اگر فرم ویرایش بود، مودال را ببند
                    if ($form.attr('id') === 'edit-form') {
                        closeModal();
                    }
                } else {
                    showAlert('danger', response.data.message || 'خطا در ذخیره اطلاعات.');
                }
            },
            error: function() {
                showAlert('danger', 'خطا در ارتباط با سرور.');
            },
            complete: function() {
                $submitBtn.text(originalText).prop('disabled', false);
                isSubmitting = false;
            }
        });
    }
    
    // تایید حذف
    function confirmDelete(id, type, name) {
        deleteItem = { id: id, type: type };
        
        // نمایش مودال حذف
        showDeleteModal(name, type === 'group' ? 'گروه' : 'سوال');
    }
    
    // نمایش مودال حذف
    function showDeleteModal(itemName, typeName) {
        const message = `شما در حال حذف ${typeName} زیر هستید:\n\n"${itemName}"\n\nاین ${typeName} به طور کامل حذف خواهد شد و قابل بازگردانی نیست.`;
        
        $('#delete-item-info').text(message);
        $('#delete-modal').show().addClass('show');
    }
    
    // بستن مودال حذف
    function closeDeleteModal() {
        $('#delete-modal').removeClass('show');
        
        // پاک کردن بعد از انیمیشن
        setTimeout(function() {
            $('#delete-modal').hide().removeClass('show');
            deleteItem = null;
        }, 300);
    }
    
    // اجرای حذف
    function executeDelete() {
        if (!deleteItem) return;
        
        const $deleteBtn = $(`.oa-btn-delete[data-id="${deleteItem.id}"]`);
        const originalText = $deleteBtn.text();
        
        // نمایش لودینگ
        $deleteBtn.text('در حال حذف...').prop('disabled', true);
        $('#confirm-delete').text('در حال حذف...').prop('disabled', true);
        
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_delete_' + deleteItem.type,
                id: deleteItem.id,
                nonce: oa_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', `${deleteItem.typeName} "${deleteItem.name}" با موفقیت حذف شد.`);
                    loadTabData(currentTab);
                    closeDeleteModal();
                } else {
                    showAlert('danger', `خطا در حذف ${deleteItem.typeName}.`);
                    $deleteBtn.text(originalText).prop('disabled', false);
                    $('#confirm-delete').text('حذف کن').prop('disabled', false);
                }
            },
            error: function() {
                showAlert('danger', 'خطا در ارتباط با سرور.');
                $deleteBtn.text(originalText).prop('disabled', false);
                $('#confirm-delete').text('حذف کن').prop('disabled', false);
            }
        });
    }
    
    // باز کردن مودال ویرایش
    function openEditModal(id, type) {
        console.log('Opening edit modal for:', id, type);
        
        // جلوگیری از کلیک‌های مکرر
        if (isModalOpening) {
            console.log('Modal is already opening, ignoring click');
            return;
        }
        isModalOpening = true;
        
        // نمایش مودال فوراً با انیمیشن
        const $editModal = $('#edit-modal');
        $editModal.show().addClass('show');
        
        // نمایش لودینگ
        const $modal = $('#edit-modal');
        $modal.find('#edit-form').html('<div class="oa-loading">در حال بارگذاری...</div>');
        
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_get_' + type,
                id: id,
                nonce: oa_admin_ajax.nonce
            },
            success: function(response) {
                console.log('Edit data response:', response);
                if (response.success) {
                    populateEditModal(response.data, type);
                } else {
                    $modal.find('#edit-form').html('<div class="oa-alert oa-alert-danger">خطا در بارگذاری اطلاعات: ' + (response.data.message || 'نامشخص') + '</div>');
                }
                isModalOpening = false;
            },
            error: function(xhr, status, error) {
                console.error('Error loading edit data:', xhr, status, error);
                $modal.find('#edit-form').html('<div class="oa-alert oa-alert-danger">خطا در ارتباط با سرور: ' + error + '</div>');
                isModalOpening = false;
            }
        });
    }
    
    // پر کردن مودال ویرایش
    function populateEditModal(data, type) {
        console.log('Populating edit modal with data:', data, 'type:', type);
        
        const $modal = $('.oa-modal');
        const $form = $modal.find('#edit-form');
        let formHtml = '';
        
        if (type === 'group') {
            formHtml = `
                <div class="oa-form-row">
                    <div class="oa-form-group">
                        <label for="edit_group_name">نام گروه:</label>
                        <input type="text" id="edit_group_name" name="name" value="${escapeHtml(data.name)}" required>
                    </div>
                    <div class="oa-form-group">
                        <label for="edit_group_order">ترتیب نمایش:</label>
                        <input type="number" id="edit_group_order" name="display_order" value="${data.display_order}" min="1" max="9">
                    </div>
                </div>
                
                <div class="oa-form-group">
                    <label for="edit_group_description">توضیحات:</label>
                    <textarea id="edit_group_description" name="description" rows="3">${escapeHtml(data.description || '')}</textarea>
                </div>
                
                <div class="oa-form-group">
                    <label for="edit_group_tips">توصیه‌ها:</label>
                    <textarea id="edit_group_tips" name="tips" rows="3">${escapeHtml(data.tips || '')}</textarea>
                </div>
                
                <div class="oa-form-group">
                    <label for="edit_group_video">لینک ویدیو:</label>
                    <div class="oa-video-input-container">
                        <input type="url" id="edit_group_video" name="video_url" value="${escapeHtml(data.video_url || '')}" placeholder="لینک آپارات، ویدیو مستقیم یا انتخاب از کتابخانه">
                        <button type="button" id="edit-select-video-btn" class="oa-btn oa-btn-secondary">انتخاب ویدیو</button>
                    </div>
                    <div class="oa-video-preview" id="edit-video-preview" style="display: none;">
                        <video controls style="max-width: 300px; max-height: 200px;">
                            <source id="edit-video-source" src="" type="video/mp4">
                            مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند.
                        </video>
                        <p class="oa-video-info" id="edit-video-info"></p>
                    </div>
                    <p class="oa-help-text">
                        می‌توانید لینک آپارات، لینک مستقیم ویدیو وارد کنید یا از دکمه "انتخاب ویدیو" برای انتخاب از کتابخانه وردپرس استفاده کنید.
                    </p>
                </div>
                
                <button type="submit" class="oa-btn oa-btn-primary">ذخیره تغییرات</button>
            `;
        } else if (type === 'question') {
            formHtml = `
                <div class="oa-form-row">
                    <div class="oa-form-group">
                        <label for="edit_question_group">گروه:</label>
                        <select id="edit_question_group" name="group_id" required>
                            <option value="">انتخاب گروه</option>
                            ${getGroupOptions(data.group_id)}
                        </select>
                    </div>
                    <div class="oa-form-group">
                        <label for="edit_question_order">ترتیب نمایش:</label>
                        <input type="number" id="edit_question_order" name="display_order" value="${data.display_order}" min="1" max="4">
                    </div>
                </div>
                
                <div class="oa-form-group">
                    <label for="edit_question_text">متن سوال:</label>
                    <textarea id="edit_question_text" name="question_text" rows="3" required>${escapeHtml(data.question_text)}</textarea>
                </div>
                
                <div class="oa-question-group">
                    <h4>گزینه‌ها:</h4>
                    ${getQuestionOptions(data.options)}
                </div>
                
                <button type="submit" class="oa-btn oa-btn-primary">ذخیره تغییرات</button>
            `;
        }
        
        $form.html(formHtml);
        $form.data('edit-id', data.id);
        $form.data('edit-type', type);
        
        // اگر نوع گروه است و ویدیو دارد، پیش‌نمایش را نمایش بده
        if (type === 'group' && data.video_url) {
            setTimeout(function() {
                updateEditVideoPreview();
            }, 100);
        }
        
        console.log('Edit modal populated successfully');
    }
    
    // تابع escape برای HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // دریافت گزینه‌های گروه برای select
    function getGroupOptions(selectedId) {
        let options = '';
        
        // اگر گروه‌ها در DOM موجود نیستند، از AJAX دریافت کنیم
        const $existingOptions = $('.oa-tab-content[data-tab="groups"] select option');
        if ($existingOptions.length === 0) {
            // بارگذاری گروه‌ها از سرور
            $.ajax({
                url: oa_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'oa_get_groups',
                    nonce: oa_admin_ajax.nonce
                },
                async: false, // Synchronous برای اطمینان از بارگذاری
                success: function(response) {
                    if (response.success) {
                        response.data.forEach(function(group) {
                            const selected = group.id == selectedId ? 'selected' : '';
                            options += `<option value="${group.id}" ${selected}>${escapeHtml(group.name)}</option>`;
                        });
                    }
                }
            });
        } else {
            // استفاده از گروه‌های موجود در DOM
            $existingOptions.each(function() {
                const value = $(this).val();
                const text = $(this).text();
                if (value && value !== '') {
                    const selected = value == selectedId ? 'selected' : '';
                    options += `<option value="${value}" ${selected}>${escapeHtml(text)}</option>`;
                }
            });
        }
        
        return options;
    }
    
    // دریافت گزینه‌های سوال
    function getQuestionOptions(options) {
        let html = '';
        for (let i = 0; i < 4; i++) {
            const option = options[i] || { option_text: '', score: i };
            html += `
                <div class="oa-option-item">
                    <label>گزینه ${i + 1}:</label>
                    <input type="text" name="options[${i}][text]" value="${escapeHtml(option.option_text)}" placeholder="متن گزینه" required>
                    <input type="number" name="options[${i}][score]" class="oa-score-input" value="${option.score}" min="0" max="3" required>
                </div>
            `;
        }
        return html;
    }
    
    // بستن مودال
    function closeModal() {
        console.log('Closing modal');
        
        $('#edit-modal').removeClass('show');
        $('#delete-modal').removeClass('show');
        
        // پاک کردن فرم بعد از انیمیشن
        setTimeout(function() {
            const $editModal = $('#edit-modal');
            const $deleteModal = $('#delete-modal');
            
            $editModal.hide().removeClass('show');
            $deleteModal.hide().removeClass('show');
            
            // Reset forms
            const $editForm = $('#edit-form');
            if ($editForm.length) {
                $editForm[0].reset();
                $editForm.removeData('edit-id edit-type');
                $editForm.empty(); // Clear form content
            }
            
            deleteItem = null;
            isModalOpening = false; // بازنشانی فلگ
            console.log('Modal closed and reset');
        }, 300);
    }
    
    // نمایش پیام
    function showAlert(type, message) {
        const alertHtml = '<div class="oa-alert oa-alert-' + type + '">' + message + '</div>';
        $('.oa-admin-content').prepend(alertHtml);
        
        setTimeout(function() {
            $('.oa-alert').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // نمایش پیام موفقیت کپی
    function showCopySuccess($button) {
        const originalText = $button.text();
        $button.text('کپی شد!').removeClass('oa-btn-secondary').addClass('oa-btn-success');
        
        setTimeout(function() {
            $button.text(originalText).removeClass('oa-btn-success').addClass('oa-btn-secondary');
        }, 2000);
    }
    
    // شروع افزونه - منتظر آماده شدن DOM
    $(document).ready(function() {
        console.log('DOM is ready, initializing plugin...');
        console.log('Found tabs:', $('.oa-admin-tab').length);
        console.log('Found tab contents:', $('.oa-tab-content').length);
        init();
    });
    
    // تابع مشاهده نتیجه (برای استفاده در HTML)
    window.viewResult = function(resultId) {
        window.open(oa_admin_ajax.ajax_url + '?action=oa_view_result&id=' + resultId, '_blank');
    };
    
    // آپلود فایل
    $('.oa-file-upload').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('.oa-file-preview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });
    
    // مرتب‌سازی جدول
    $('.oa-table th').on('click', function() {
        const column = $(this).index();
        const $table = $(this).closest('table');
        const $rows = $table.find('tbody tr').toArray();
        
        $rows.sort(function(a, b) {
            const aVal = $(a).find('td').eq(column).text();
            const bVal = $(b).find('td').eq(column).text();
            return aVal.localeCompare(bVal, 'fa');
        });
        
        $table.find('tbody').empty().append($rows);
    });
    
        // جستجو در جدول
        $('.oa-search-input').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $table = $(this).closest('.oa-tab-content').find('.oa-table');
            
            $table.find('tbody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                if (rowText.indexOf(searchTerm) === -1) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });
        });
        
        // دکمه‌های کپی در تب راهنما
        $(document).on('click', '.oa-copy-btn', function(e) {
            e.preventDefault();
            const textToCopy = $(this).data('copy');
            
            // کپی کردن به clipboard
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showCopySuccess($(this));
                }.bind(this));
            } else {
                // Fallback برای مرورگرهای قدیمی
                const textArea = document.createElement('textarea');
                textArea.value = textToCopy;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showCopySuccess($(this));
                } catch (err) {
                    console.error('Unable to copy text: ', err);
                }
                document.body.removeChild(textArea);
            }
        });
    });
    
    // توابع مدیریت ویدیو
    function openMediaLibrary() {
        if (typeof wp !== 'undefined' && wp.media) {
            const frame = wp.media({
                title: 'انتخاب ویدیو',
                button: {
                    text: 'انتخاب ویدیو'
                },
                library: {
                    type: 'video'
                },
                multiple: false
            });
            
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#group_video').val(attachment.url);
                updateVideoPreview();
            });
            
            frame.open();
        } else {
            alert('کتابخانه رسانه وردپرس در دسترس نیست.');
        }
    }
    
    function updateVideoPreview() {
        const videoUrl = $('#group_video').val();
        const preview = $('#video-preview');
        const videoSource = $('#video-source');
        const videoInfo = $('#video-info');
        
        if (videoUrl) {
            // بررسی نوع لینک
            if (isAparatUrl(videoUrl)) {
                // برای آپارات، لینک embed را ایجاد می‌کنیم
                const embedUrl = convertAparatToEmbed(videoUrl);
                videoSource.attr('src', embedUrl);
                videoInfo.text('ویدیو آپارات: ' + videoUrl);
            } else if (isDirectVideoUrl(videoUrl)) {
                // برای ویدیو مستقیم
                videoSource.attr('src', videoUrl);
                videoInfo.text('ویدیو مستقیم: ' + videoUrl);
            } else {
                // لینک نامعتبر
                videoInfo.text('لینک نامعتبر: ' + videoUrl);
                videoSource.attr('src', '');
            }
            
            preview.show();
        } else {
            preview.hide();
        }
    }
    
    function isAparatUrl(url) {
        return url.includes('aparat.com') || url.includes('aparat.ir');
    }
    
    function isDirectVideoUrl(url) {
        const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.wmv'];
        return videoExtensions.some(ext => url.toLowerCase().includes(ext));
    }
    
    function convertAparatToEmbed(url) {
        // تبدیل لینک آپارات به embed
        // مثال: https://www.aparat.com/v/ABC123 -> https://www.aparat.com/video/video/embed/videohash/ABC123
        const match = url.match(/aparat\.com\/v\/([^\/\?]+)/);
        if (match) {
            return 'https://www.aparat.com/video/video/embed/videohash/' + match[1];
        }
        return url;
    }
    
    // توابع مخصوص مودال ویرایش
    function openEditMediaLibrary() {
        if (typeof wp !== 'undefined' && wp.media) {
            const frame = wp.media({
                title: 'انتخاب ویدیو',
                button: {
                    text: 'انتخاب ویدیو'
                },
                library: {
                    type: 'video'
                },
                multiple: false
            });
            
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#edit_group_video').val(attachment.url);
                updateEditVideoPreview();
            });
            
            frame.open();
        } else {
            alert('کتابخانه رسانه وردپرس در دسترس نیست.');
        }
    }
    
    function updateEditVideoPreview() {
        const videoUrl = $('#edit_group_video').val();
        const preview = $('#edit-video-preview');
        const videoSource = $('#edit-video-source');
        const videoInfo = $('#edit-video-info');
        
        if (videoUrl) {
            // بررسی نوع لینک
            if (isAparatUrl(videoUrl)) {
                // برای آپارات، لینک embed را ایجاد می‌کنیم
                const embedUrl = convertAparatToEmbed(videoUrl);
                videoSource.attr('src', embedUrl);
                videoInfo.text('ویدیو آپارات: ' + videoUrl);
            } else if (isDirectVideoUrl(videoUrl)) {
                // برای ویدیو مستقیم
                videoSource.attr('src', videoUrl);
                videoInfo.text('ویدیو مستقیم: ' + videoUrl);
            } else {
                // لینک نامعتبر
                videoInfo.text('لینک نامعتبر: ' + videoUrl);
                videoSource.attr('src', '');
            }
            
            preview.show();
        } else {
            preview.hide();
        }
    }
    
}); // پایان jQuery(document).ready
