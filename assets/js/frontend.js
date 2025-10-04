jQuery(document).ready(function($) {
    'use strict';
    
    // متغیرهای عمومی
    let currentQuestion = 0;
    let totalQuestions = 0;
    let answers = [];
    let isSubmitting = false;
    let isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    // تابع تبدیل اعداد انگلیسی به فارسی
    function convertToPersianNumbers(str) {
        const english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        const persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str.toString().replace(/[0-9]/g, function(match) {
            return persian[english.indexOf(match)];
        });
    }
    
    // مقداردهی اولیه
    function init() {
        totalQuestions = $('.oa-question').length;
        answers = new Array(totalQuestions).fill(null);
        updateProgress();
        showQuestion(0);
        
        // رویداد تغییر گزینه
        $('.oa-option input[type="radio"]').on('change', function() {
            const questionIndex = $(this).closest('.oa-question').data('question-index');
            const optionIndex = $(this).closest('.oa-option').data('option-index');
            answers[questionIndex] = optionIndex;
            
            // فعال کردن دکمه بعدی
            const nextBtn = $(this).closest('.oa-question').find('.oa-btn-next');
            if (nextBtn.length) {
                nextBtn.prop('disabled', false);
            }
        });
        
        // رویداد کلیک دکمه بعدی
        $('.oa-btn-next').on('click', function(e) {
            e.preventDefault();
            const questionIndex = $(this).closest('.oa-question').data('question-index');
            if (answers[questionIndex] !== null) {
                nextQuestion();
            }
        });
        
        // رویداد کلیک دکمه قبلی
        $('.oa-btn-prev').on('click', function(e) {
            e.preventDefault();
            prevQuestion();
        });
        
        // رویداد ارسال فرم
        $('.oa-quiz-form').on('submit', function(e) {
            e.preventDefault();
            if (!isSubmitting) {
                submitQuiz();
            }
        });
    }
    
    // نمایش سوال فعلی
    function showQuestion(index) {
        $('.oa-question').hide();
        $('.oa-question').eq(index).show();
        
        // تنظیم دکمه‌ها
        const $currentQuestion = $('.oa-question').eq(index);
        const $prevBtn = $currentQuestion.find('.oa-btn-prev');
        const $nextBtn = $currentQuestion.find('.oa-btn-next');
        
        // دکمه قبلی
        if (index === 0) {
            $prevBtn.hide();
        } else {
            $prevBtn.show();
        }
        
        // دکمه بعدی
        if (index === totalQuestions - 1) {
            $nextBtn.hide();
            $currentQuestion.find('.oa-submit-btn').show();
        } else {
            $nextBtn.show();
            $currentQuestion.find('.oa-submit-btn').hide();
        }
        
        // بررسی پاسخ انتخاب شده
        if (answers[index] !== null) {
            $currentQuestion.find('.oa-option').eq(answers[index]).find('input').prop('checked', true);
            $nextBtn.prop('disabled', false);
        } else {
            $nextBtn.prop('disabled', true);
        }
        
        currentQuestion = index;
        updateProgress();
    }
    
    // سوال بعدی
    function nextQuestion() {
        if (currentQuestion < totalQuestions - 1) {
            showQuestion(currentQuestion + 1);
        }
    }
    
    // سوال قبلی
    function prevQuestion() {
        if (currentQuestion > 0) {
            showQuestion(currentQuestion - 1);
        }
    }
    
    // به‌روزرسانی نوار پیشرفت
    function updateProgress() {
        const progress = ((currentQuestion + 1) / totalQuestions) * 100;
        $('.oa-progress-fill').css('width', progress + '%');
        $('.oa-progress-text').text(`سوال ${convertToPersianNumbers(currentQuestion + 1)} از ${convertToPersianNumbers(totalQuestions)}`);
    }
    
    // ارسال تست
    function submitQuiz() {
        // بررسی تکمیل همه سوالات
        const unansweredQuestions = answers.filter(answer => answer === null);
        if (unansweredQuestions.length > 0) {
            alert('لطفاً به همه سوالات پاسخ دهید.');
            return;
        }
        
        isSubmitting = true;
        
        // نمایش لودینگ
        $('.oa-submit-btn').html('<span class="oa-loading"></span> در حال پردازش...');
        $('.oa-submit-btn').prop('disabled', true);
        
        // ارسال داده‌ها
        $.ajax({
            url: oa_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'oa_submit_quiz',
                nonce: oa_ajax.nonce,
                answers: answers
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect_url;
                } else {
                    alert('خطا در پردازش تست. لطفاً دوباره تلاش کنید.');
                    resetSubmitButton();
                }
            },
            error: function() {
                alert('خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.');
                resetSubmitButton();
            }
        });
    }
    
    // بازنشانی دکمه ارسال
    function resetSubmitButton() {
        $('.oa-submit-btn').html('ارسال تست');
        $('.oa-submit-btn').prop('disabled', false);
        isSubmitting = false;
    }
    
    // شروع افزونه
    init();
    
    // رویدادهای صفحه نتیجه
    if ($('.oa-result-container').length) {
        // پخش خودکار ویدیو
        $('.oa-video-wrapper video').on('loadeddata', function() {
            $(this).attr('controls', 'controls');
        });
        
        // دکمه تکرار تست
        $('.oa-retake-btn .oa-btn').on('click', function(e) {
            e.preventDefault();
            window.location.href = window.location.origin + '/';
        });
    }
    
    // انیمیشن‌های ورودی
    $('.oa-question').each(function(index) {
        $(this).css({
            'opacity': '0',
            'transform': 'translateX(50px)'
        });
        
        setTimeout(() => {
            $(this).css({
                'opacity': '1',
                'transform': 'translateX(0)',
                'transition': 'all 0.5s ease'
            });
        }, index * 100);
    });
    
    // انیمیشن انتخاب گزینه (بهینه شده برای تاچ)
    $('.oa-option label').on('click touchstart', function(e) {
        if (isTouchDevice) {
            e.preventDefault();
        }
        
        $(this).closest('.oa-option').addClass('selected');
        
        setTimeout(() => {
            $(this).closest('.oa-option').removeClass('selected');
        }, 300);
    });
    
    // بهبود تجربه تاچ در موبایل
    if (isTouchDevice) {
        $('.oa-option label').css({
            'cursor': 'pointer',
            'user-select': 'none',
            '-webkit-tap-highlight-color': 'transparent'
        });
        
        $('.oa-btn').css({
            'cursor': 'pointer',
            'user-select': 'none',
            '-webkit-tap-highlight-color': 'transparent'
        });
    }
    
    // اسکرول به سوال بعدی (بهینه شده برای موبایل)
    function scrollToNextQuestion() {
        const offset = $(window).width() < 768 ? 80 : 100; // کمتر در موبایل
        $('html, body').animate({
            scrollTop: $('.oa-question').eq(currentQuestion).offset().top - offset
        }, 300); // سریع‌تر در موبایل
    }
    
    // اضافه کردن اسکرول خودکار
    $('.oa-btn-next').on('click', function() {
        setTimeout(scrollToNextQuestion, 100);
    });
    
    // کیبورد شورتکات
    $(document).on('keydown', function(e) {
        if ($('.oa-quiz-container').length) {
            // فلش راست: سوال بعدی
            if (e.keyCode === 39) {
                e.preventDefault();
                if (currentQuestion < totalQuestions - 1 && answers[currentQuestion] !== null) {
                    nextQuestion();
                }
            }
            // فلش چپ: سوال قبلی
            else if (e.keyCode === 37) {
                e.preventDefault();
                if (currentQuestion > 0) {
                    prevQuestion();
                }
            }
            // اعداد 1-4: انتخاب گزینه
            else if (e.keyCode >= 49 && e.keyCode <= 52) {
                e.preventDefault();
                const optionIndex = e.keyCode - 49;
                const $currentQuestion = $('.oa-question').eq(currentQuestion);
                const $option = $currentQuestion.find('.oa-option').eq(optionIndex);
                
                if ($option.length) {
                    $option.find('input').prop('checked', true).trigger('change');
                }
            }
        }
    });
    
    // نمایش راهنمای کیبورد فقط در دسکتاپ
    if ($('.oa-quiz-container').length && $(window).width() > 768) {
        $('<div class="oa-keyboard-help" style="margin-top: 10px; padding: 8px; background: rgba(255,255,255,0.2); border-radius: 5px; font-size: 12px; text-align: center;">' +
          '<small>راهنما: از کلیدهای ← → برای حرکت بین سوالات و اعداد 1-4 برای انتخاب گزینه استفاده کنید</small>' +
          '</div>').appendTo('.oa-quiz-header');
    }
});
