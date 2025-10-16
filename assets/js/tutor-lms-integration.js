/**
 * JavaScript مخصوص هماهنگی با Tutor LMS
 * این فایل قابلیت‌های تعاملی آزمون در درس‌های Tutor LMS را فراهم می‌کند
 */

jQuery(document).ready(function($) {
    
    // بررسی وجود آزمون در Tutor LMS
    if ($('.oa-tutor-quiz-section').length) {
        initTutorQuizIntegration();
    }
    
    /**
     * راه‌اندازی هماهنگی آزمون با Tutor LMS
     */
    function initTutorQuizIntegration() {
        console.log('Tutor LMS Quiz Integration initialized');
        
        // اضافه کردن رویدادهای مخصوص Tutor LMS
        addTutorQuizEvents();
        
        // تنظیم استایل‌های مخصوص Tutor LMS
        applyTutorStyles();
        
        // اضافه کردن قابلیت‌های پیشرفته
        addAdvancedFeatures();
    }
    
    /**
     * اضافه کردن رویدادهای مخصوص Tutor LMS
     */
    function addTutorQuizEvents() {
        // رویداد شروع آزمون
        $(document).on('click', '.oa-tutor-quiz-section .oa-btn-next:first', function() {
            trackQuizStart();
        });
        
        // رویداد تکمیل آزمون
        $(document).on('click', '.oa-tutor-quiz-section .oa-submit-btn', function() {
            trackQuizCompletion();
        });
        
        // رویداد تکمیل آزمون جدید
        $(document).on('click', '.oa-tutor-submit-btn', function() {
            trackQuizCompletion();
        });
        
        // رویداد تغییر سوال
        $(document).on('click', '.oa-tutor-quiz-section .oa-btn-next', function() {
            trackQuestionChange();
        });
        
        // رویداد انتخاب گزینه
        $(document).on('change', '.oa-tutor-quiz-section input[type="radio"]', function() {
            trackOptionSelection($(this));
        });
    }
    
    /**
     * اعمال استایل‌های مخصوص Tutor LMS
     */
    function applyTutorStyles() {
        // اضافه کردن کلاس مخصوص Tutor LMS
        $('.oa-tutor-quiz-section').addClass('tutor-integrated');
        
        // تنظیم ارتفاع مناسب برای آزمون
        $('.oa-tutor-quiz-section').css('min-height', '400px');
        
        // اضافه کردن انیمیشن ورود
        $('.oa-tutor-quiz-section').hide().fadeIn(500);
    }
    
    /**
     * اضافه کردن قابلیت‌های پیشرفته
     */
    function addAdvancedFeatures() {
        // اضافه کردن دکمه بازگشت به درس
        addBackToLessonButton();
        
        // اضافه کردن نمایش پیشرفت در نوار کناری
        addProgressSidebar();
        
        // اضافه کردن قابلیت ذخیره خودکار
        addAutoSave();
        
        // اضافه کردن قابلیت راهنمایی
        addHelpFeature();
    }
    
    /**
     * اضافه کردن دکمه بازگشت به درس
     */
    function addBackToLessonButton() {
        var backButton = $('<button type="button" class="oa-btn oa-btn-secondary oa-back-to-lesson">بازگشت به درس</button>');
        
        backButton.on('click', function() {
            // بازگشت به درس قبلی در Tutor LMS
            if (typeof tutorUtils !== 'undefined' && tutorUtils.goToPreviousLesson) {
                tutorUtils.goToPreviousLesson();
            } else {
                // بازگشت به صفحه درس
                window.history.back();
            }
        });
        
        $('.oa-tutor-quiz-section .oa-navigation').prepend(backButton);
    }
    
    /**
     * اضافه کردن نمایش پیشرفت در نوار کناری
     */
    function addProgressSidebar() {
        var progressSidebar = $('<div class="oa-progress-sidebar"><h4>پیشرفت آزمون</h4><div class="oa-progress-list"></div></div>');
        
        // اضافه کردن به کنار صفحه
        $('body').append(progressSidebar);
        
        // به‌روزرسانی پیشرفت
        updateProgressSidebar();
    }
    
    /**
     * اضافه کردن قابلیت ذخیره خودکار
     */
    function addAutoSave() {
        var autoSaveInterval = setInterval(function() {
            if ($('.oa-tutor-quiz-section .oa-question:visible').length > 0) {
                saveCurrentProgress();
            }
        }, 30000); // هر 30 ثانیه
        
        // ذخیره هنگام تغییر سوال
        $(document).on('click', '.oa-tutor-quiz-section .oa-btn-next', function() {
            saveCurrentProgress();
        });
    }
    
    /**
     * اضافه کردن قابلیت راهنمایی
     */
    function addHelpFeature() {
        var helpButton = $('<button type="button" class="oa-help-btn" title="راهنمایی">?</button>');
        
        helpButton.on('click', function() {
            showHelpModal();
        });
        
        $('.oa-tutor-quiz-section h3').after(helpButton);
    }
    
    /**
     * نمایش مودال راهنمایی
     */
    function showHelpModal() {
        var helpContent = `
            <div class="oa-help-modal">
                <div class="oa-help-content">
                    <h3>راهنمای آزمون تشخیص چاقی</h3>
                    <ul>
                        <li>به همه سوالات با دقت پاسخ دهید</li>
                        <li>پاسخ‌های شما محرمانه است</li>
                        <li>می‌توانید در هر زمان آزمون را متوقف کنید</li>
                        <li>نتایج آزمون در پروفایل شما ذخیره می‌شود</li>
                    </ul>
                    <button type="button" class="oa-btn oa-btn-primary oa-close-help">بستن</button>
                </div>
            </div>
        `;
        
        $('body').append(helpContent);
        
        // رویداد بستن مودال
        $('.oa-close-help').on('click', function() {
            $('.oa-help-modal').remove();
        });
        
        // بستن با کلیک خارج از مودال
        $('.oa-help-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).remove();
            }
        });
    }
    
    /**
     * ردیابی شروع آزمون
     */
    function trackQuizStart() {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'quiz_start', {
                'event_category': 'tutor_lms',
                'event_label': 'obesity_assessment'
            });
        }
        
        // ذخیره زمان شروع
        localStorage.setItem('oa_quiz_start_time', Date.now());
    }
    
    /**
     * ردیابی تکمیل آزمون
     */
    function trackQuizCompletion() {
        var startTime = localStorage.getItem('oa_quiz_start_time');
        var duration = startTime ? Date.now() - startTime : 0;
        
        if (typeof gtag !== 'undefined') {
            gtag('event', 'quiz_complete', {
                'event_category': 'tutor_lms',
                'event_label': 'obesity_assessment',
                'value': Math.round(duration / 1000) // مدت زمان به ثانیه
            });
        }
        
        // پاک کردن زمان شروع
        localStorage.removeItem('oa_quiz_start_time');
    }
    
    /**
     * ردیابی تغییر سوال
     */
    function trackQuestionChange() {
        var currentQuestion = $('.oa-tutor-quiz-section .oa-question:visible').data('question-index');
        
        if (typeof gtag !== 'undefined') {
            gtag('event', 'question_change', {
                'event_category': 'tutor_lms',
                'event_label': 'question_' + currentQuestion
            });
        }
    }
    
    /**
     * ردیابی انتخاب گزینه
     */
    function trackOptionSelection($input) {
        var questionId = $input.closest('.oa-question').data('question-id');
        var optionIndex = $input.val();
        
        if (typeof gtag !== 'undefined') {
            gtag('event', 'option_select', {
                'event_category': 'tutor_lms',
                'event_label': 'question_' + questionId + '_option_' + optionIndex
            });
        }
    }
    
    /**
     * ذخیره پیشرفت فعلی
     */
    function saveCurrentProgress() {
        var answers = {};
        
        $('.oa-tutor-quiz-section .oa-question').each(function() {
            var questionId = $(this).data('question-id');
            var selectedOption = $(this).find('input[type="radio"]:checked').val();
            
            if (selectedOption !== undefined) {
                answers[questionId] = selectedOption;
            }
        });
        
        // ذخیره در localStorage
        localStorage.setItem('oa_quiz_progress', JSON.stringify(answers));
        
        // نمایش پیام ذخیره
        showSaveMessage();
    }
    
    /**
     * نمایش پیام ذخیره
     */
    function showSaveMessage() {
        var saveMessage = $('<div class="oa-save-message">پیشرفت ذخیره شد</div>');
        
        $('.oa-tutor-quiz-section').append(saveMessage);
        
        setTimeout(function() {
            saveMessage.fadeOut(300, function() {
                $(this).remove();
            });
        }, 2000);
    }
    
    /**
     * به‌روزرسانی نوار کناری پیشرفت
     */
    function updateProgressSidebar() {
        var totalQuestions = $('.oa-tutor-quiz-section .oa-question').length;
        var answeredQuestions = $('.oa-tutor-quiz-section .oa-question').filter(function() {
            return $(this).find('input[type="radio"]:checked').length > 0;
        }).length;
        
        var progressPercentage = Math.round((answeredQuestions / totalQuestions) * 100);
        
        $('.oa-progress-sidebar .oa-progress-list').html(`
            <div class="oa-progress-item">
                <span>سوالات پاسخ داده شده:</span>
                <span>${answeredQuestions} از ${totalQuestions}</span>
            </div>
            <div class="oa-progress-item">
                <span>درصد پیشرفت:</span>
                <span>${progressPercentage}%</span>
            </div>
        `);
    }
    
    /**
     * بارگذاری پیشرفت ذخیره شده
     */
    function loadSavedProgress() {
        var savedProgress = localStorage.getItem('oa_quiz_progress');
        
        if (savedProgress) {
            var answers = JSON.parse(savedProgress);
            
            $.each(answers, function(questionId, optionIndex) {
                $('.oa-tutor-quiz-section .oa-question[data-question-id="' + questionId + '"]')
                    .find('input[type="radio"][value="' + optionIndex + '"]')
                    .prop('checked', true);
            });
            
            // به‌روزرسانی نوار پیشرفت
            updateProgressSidebar();
        }
    }
    
    // بارگذاری پیشرفت ذخیره شده هنگام بارگذاری صفحه
    loadSavedProgress();
    
    // به‌روزرسانی نوار پیشرفت هنگام تغییر پاسخ
    $(document).on('change', '.oa-tutor-quiz-section input[type="radio"]', function() {
        updateProgressSidebar();
    });
    
});

// استایل‌های CSS اضافی برای قابلیت‌های جدید
var tutorIntegrationStyles = `
    .oa-progress-sidebar {
        position: fixed;
        top: 50%;
        right: 20px;
        transform: translateY(-50%);
        background: #ffffff;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        min-width: 200px;
    }
    
    .oa-progress-sidebar h4 {
        margin: 0 0 15px 0;
        color: #2c3e50;
        font-size: 16px;
    }
    
    .oa-progress-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .oa-help-btn {
        background: #3498db;
        color: #ffffff;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        font-size: 16px;
        cursor: pointer;
        margin-right: 10px;
        vertical-align: middle;
    }
    
    .oa-help-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    .oa-help-content {
        background: #ffffff;
        padding: 30px;
        border-radius: 8px;
        max-width: 500px;
        width: 90%;
    }
    
    .oa-help-content h3 {
        margin-top: 0;
        color: #2c3e50;
    }
    
    .oa-help-content ul {
        margin: 20px 0;
        padding-right: 20px;
    }
    
    .oa-help-content li {
        margin-bottom: 10px;
        color: #555;
    }
    
    .oa-save-message {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #27ae60;
        color: #ffffff;
        padding: 10px 20px;
        border-radius: 5px;
        z-index: 1000;
    }
    
    .oa-back-to-lesson {
        background: #95a5a6 !important;
    }
    
    .oa-back-to-lesson:hover {
        background: #7f8c8d !important;
    }
    
    @media (max-width: 768px) {
        .oa-progress-sidebar {
            display: none;
        }
    }
`;

// اضافه کردن استایل‌ها به صفحه
var styleSheet = document.createElement('style');
styleSheet.textContent = tutorIntegrationStyles;
document.head.appendChild(styleSheet);
