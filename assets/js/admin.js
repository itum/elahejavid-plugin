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
        // تب‌ها
        $('.oa-admin-tab').on('click', function() {
            const tabId = $(this).data('tab');
            switchTab(tabId);
        });
        
        // فرم‌ها
        $(document).on('submit', '.oa-form', function(e) {
            e.preventDefault();
            handleFormSubmit($(this));
        });
        
        // دکمه‌های حذف و ویرایش (delegated events)
        $(document).on('click', '.oa-btn-delete', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const type = $(this).data('type');
            const name = $(this).data('name');
            confirmDelete(id, type, name);
        });
        
        $(document).on('click', '.oa-btn-edit', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            const type = $(this).data('type');
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
        $('.oa-admin-tab').removeClass('active');
        $('.oa-admin-tab[data-tab="' + tabId + '"]').addClass('active');
        
        $('.oa-tab-content').removeClass('active');
        $('.oa-tab-content[data-tab="' + tabId + '"]').addClass('active');
        
        currentTab = tabId;
        
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
            html += '<button class="oa-btn oa-btn-danger oa-btn-small oa-btn-delete" data-id="' + group.id + '" data-type="group">حذف</button>';
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
            html += '<button class="oa-btn oa-btn-danger oa-btn-small oa-btn-delete" data-id="' + question.id + '" data-type="question">حذف</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('.oa-tab-content[data-tab="questions"] .oa-table-container').html(html);
    }
    
    // رندر جدول نتایج
    function renderResultsTable(results) {
        let html = '<table class="oa-table">';
        html += '<thead><tr><th>تاریخ</th><th>کاربر</th><th>گروه‌های برنده</th><th>امتیازات</th><th>عملیات</th></tr></thead>';
        html += '<tbody>';
        
        results.forEach(function(result) {
            // فرمت تاریخ
            const date = new Date(result.created_at);
            const formattedDate = date.toLocaleDateString('fa-IR') + ' ' + date.toLocaleTimeString('fa-IR', {hour: '2-digit', minute: '2-digit'});
            
            // تشخیص نوع کاربر
            let userDisplay = 'مهمان';
            if (result.user_id && result.user_id > 0) {
                userDisplay = result.user_name || 'کاربر ثبت‌شده';
            } else if (result.session_id) {
                userDisplay = 'کاربر موقت (Session)';
            }
            
            html += '<tr>';
            html += '<td>' + formattedDate + '</td>';
            html += '<td>' + userDisplay + '</td>';
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
        let actionName = 'oa_save_' + currentTab.slice(0, -1);
        if ($form.attr('id') === 'edit-form') {
            const editType = $form.data('edit-type');
            const editId = $form.data('edit-id');
            actionName = 'oa_save_' + editType;
            formData.append('edit_id', editId);
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
        $('#delete-modal').addClass('show');
    }
    
    // بستن مودال حذف
    function closeDeleteModal() {
        $('#delete-modal').removeClass('show');
        
        // پاک کردن بعد از انیمیشن
        setTimeout(function() {
            $('#delete-modal').hide();
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
        // جلوگیری از کلیک‌های مکرر
        if (isModalOpening) return;
        isModalOpening = true;
        
        // نمایش مودال فوراً با انیمیشن
        $('#edit-modal').addClass('show');
        
        // نمایش لودینگ فقط یک بار
        const $modal = $('#edit-modal');
        if ($modal.find('#edit-form').html().trim() === '') {
            $modal.find('#edit-form').html('<div class="oa-loading">در حال بارگذاری...</div>');
        }
        
        $.ajax({
            url: oa_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_get_' + type,
                id: id,
                nonce: oa_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateEditModal(response.data, type);
                } else {
                    $modal.find('#edit-form').html('<div class="oa-alert oa-alert-danger">خطا در بارگذاری اطلاعات</div>');
                }
                isModalOpening = false;
            },
            error: function() {
                $modal.find('#edit-form').html('<div class="oa-alert oa-alert-danger">خطا در ارتباط با سرور</div>');
                isModalOpening = false;
            }
        });
    }
    
    // پر کردن مودال ویرایش
    function populateEditModal(data, type) {
        const $modal = $('.oa-modal');
        let formHtml = '';
        
        if (type === 'group') {
            formHtml = `
                <div class="oa-form-row">
                    <div class="oa-form-group">
                        <label for="edit_group_name">نام گروه:</label>
                        <input type="text" id="edit_group_name" name="name" value="${data.name}" required>
                    </div>
                    <div class="oa-form-group">
                        <label for="edit_group_order">ترتیب نمایش:</label>
                        <input type="number" id="edit_group_order" name="display_order" value="${data.display_order}" min="1" max="9">
                    </div>
                </div>
                
                <div class="oa-form-group">
                    <label for="edit_group_description">توضیحات:</label>
                    <textarea id="edit_group_description" name="description" rows="3">${data.description || ''}</textarea>
                </div>
                
                <div class="oa-form-group">
                    <label for="edit_group_tips">توصیه‌ها:</label>
                    <textarea id="edit_group_tips" name="tips" rows="3">${data.tips || ''}</textarea>
                </div>
                
                <div class="oa-form-group">
                    <label for="edit_group_video">لینک ویدیو:</label>
                    <input type="url" id="edit_group_video" name="video_url" value="${data.video_url || ''}" placeholder="https://example.com/video.mp4">
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
                    <textarea id="edit_question_text" name="question_text" rows="3" required>${data.question_text}</textarea>
                </div>
                
                <div class="oa-question-group">
                    <h4>گزینه‌ها:</h4>
                    ${getQuestionOptions(data.options)}
                </div>
                
                <button type="submit" class="oa-btn oa-btn-primary">ذخیره تغییرات</button>
            `;
        }
        
        $modal.find('#edit-form').html(formHtml);
        $modal.find('#edit-form').data('edit-id', data.id);
        $modal.find('#edit-form').data('edit-type', type);
    }
    
    // دریافت گزینه‌های گروه برای select
    function getGroupOptions(selectedId) {
        let options = '';
        // گروه‌ها را از جدول موجود دریافت می‌کنیم
        $('.oa-tab-content[data-tab="groups"] select option').each(function() {
            const value = $(this).val();
            const text = $(this).text();
            if (value && value !== '') {
                const selected = value == selectedId ? 'selected' : '';
                options += `<option value="${value}" ${selected}>${text}</option>`;
            }
        });
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
                    <input type="text" name="options[${i}][text]" value="${option.option_text}" placeholder="متن گزینه" required>
                    <input type="number" name="options[${i}][score]" class="oa-score-input" value="${option.score}" min="0" max="3" required>
                </div>
            `;
        }
        return html;
    }
    
    // بستن مودال
    function closeModal() {
        $('#edit-modal').removeClass('show');
        $('#delete-modal').removeClass('show');
        
        // پاک کردن فرم بعد از انیمیشن
        setTimeout(function() {
            $('#edit-modal').hide();
            $('#delete-modal').hide();
            $('.oa-modal form')[0].reset();
            $('.oa-modal form').removeData('edit-id edit-type');
            deleteItem = null;
            isModalOpening = false; // بازنشانی فلگ
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
    
    // شروع افزونه
    init();
    
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
});
