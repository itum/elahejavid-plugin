<?php
/**
 * Plugin Name: تست تشخیص نوع چاقی
 * Plugin URI: https://elahejavid.ir
 * Description: افزونه تست تشخیص نوع چاقی با 9 گروه مختلف و مدیریت داینامیک سوالات
 * Version: 1.0.5
 * Author: منصور شوکت
 * Text Domain: obesity-assessment
 * Domain Path: /languages
 */

// جلوگیری از دسترسی مستقیم
if (!defined('ABSPATH')) {
    exit;
}

// تعریف ثابت‌های افزونه
define('OA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OA_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('OA_PLUGIN_VERSION', '1.0.0');

class ObesityAssessment {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // شروع session
        if (!session_id()) {
            session_start();
        }
        
        // بارگذاری ترجمه
        load_plugin_textdomain('obesity-assessment', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // اضافه کردن استایل‌ها و اسکریپت‌ها
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // ایجاد جداول دیتابیس
        add_action('wp_loaded', array($this, 'create_tables'));
        
        // اضافه کردن منوی ادمین
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // ثبت شورت‌کد
        add_shortcode('oa_quiz', array($this, 'quiz_shortcode'));
        
        // اضافه کردن منوی سایت
        add_action('wp_nav_menu_items', array($this, 'add_menu_item'), 10, 2);
        
        // پردازش فرم
        add_action('wp_ajax_oa_submit_quiz', array($this, 'submit_quiz'));
        add_action('wp_ajax_nopriv_oa_submit_quiz', array($this, 'submit_quiz'));
        
        // بارگذاری AJAX handlers
        require_once OA_PLUGIN_PATH . 'admin/ajax-handlers.php';
        
        // تست AJAX ساده
        add_action('wp_ajax_oa_test', array($this, 'test_ajax'));
        
        // صفحه نتیجه
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'template_redirect'));
    }
    
    public function activate() {
        $this->create_tables();
        $this->populate_default_data();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('oa-frontend-style', OA_PLUGIN_URL . 'assets/css/frontend.css', array(), OA_PLUGIN_VERSION);
        wp_enqueue_script('oa-frontend-script', OA_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), OA_PLUGIN_VERSION, true);
        
        wp_localize_script('oa-frontend-script', 'oa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oa_quiz_nonce')
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'obesity-assessment') !== false) {
            wp_enqueue_style('oa-admin-style', OA_PLUGIN_URL . 'assets/css/admin.css', array(), OA_PLUGIN_VERSION);
            wp_enqueue_script('oa-admin-script', OA_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), OA_PLUGIN_VERSION, true);
            
            wp_localize_script('oa-admin-script', 'oa_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oa_admin_nonce')
            ));
        }
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // جدول گروه‌ها
        $groups_table = $wpdb->prefix . 'oa_groups';
        $groups_sql = "CREATE TABLE $groups_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            tips text,
            video_url varchar(500),
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // جدول سوالات
        $questions_table = $wpdb->prefix . 'oa_questions';
        $questions_sql = "CREATE TABLE $questions_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            group_id int(11) NOT NULL,
            question_text text NOT NULL,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY group_id (group_id)
        ) $charset_collate;";
        
        // جدول گزینه‌ها
        $options_table = $wpdb->prefix . 'oa_options';
        $options_sql = "CREATE TABLE $options_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            question_id int(11) NOT NULL,
            option_text text NOT NULL,
            score int(11) DEFAULT 0,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY question_id (question_id)
        ) $charset_collate;";
        
        // جدول نتایج کاربران
        $results_table = $wpdb->prefix . 'oa_results';
        $results_sql = "CREATE TABLE $results_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11),
            session_id varchar(255),
            group_scores text,
            winning_groups text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($groups_sql);
        dbDelta($questions_sql);
        dbDelta($options_sql);
        dbDelta($results_sql);
    }
    
    public function populate_default_data() {
        global $wpdb;
        
        // بررسی وجود داده
        $existing_groups = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oa_groups");
        if ($existing_groups > 0) {
            return;
        }
        
        // گروه‌های پیش‌فرض
        $groups = array(
            array(
                'name' => 'چاقی هورمونی',
                'description' => 'چربی در شکم/پهلو و دشواری کاهش وزن',
                'tips' => 'آزمایش تیروئید؛ کاهش قند ساده؛ خواب کافی',
                'video_url' => 'https://elahejavid.ir/wp-content/uploads/2025/09/F04-00.mp4',
                'order' => 1
            ),
            array(
                'name' => 'چاقی استرسی',
                'description' => 'پرخوری در استرس + چربی شکمی',
                'tips' => 'تمرین تنفس، میان‌وعده سالم، کاهش کافئین',
                'video_url' => 'https://elahejavid.ir/wp-content/uploads/2025/09/F4-01.mp4',
                'order' => 2
            ),
            array(
                'name' => 'چاقی متابولیک',
                'description' => 'افزایش وزن ناگهانی + خستگی',
                'tips' => 'پیاده‌روی روزانه؛ وعده‌های منظم؛ بررسی تیروئید',
                'video_url' => 'https://elahejavid.ir/wp-content/uploads/2025/09/F4-0.mp4',
                'order' => 3
            ),
            array(
                'name' => 'چاقی احساسی',
                'description' => 'پرخوری از احساسات',
                'tips' => 'جایگزین رفتاری؛ ژورنال احساس؛ کنترل محیط',
                'video_url' => 'https://elahejavid.ir/wp-content/uploads/2025/09/F4-02.mp4',
                'order' => 4
            ),
            array(
                'name' => 'چاقی ژنتیکی',
                'description' => 'زمینه خانوادگی قوی',
                'tips' => 'هدف واقع‌بینانه؛ ورزش قدرتی؛ پیگیری مستمر',
                'video_url' => 'https://elahejavid.ir/wp-content/uploads/2025/09/F4-04.mp4',
                'order' => 5
            ),
            array(
                'name' => 'چاقی یویویی',
                'description' => 'چرخه کاهش و برگشت',
                'tips' => 'کالری متوسط پایدار؛ تمرکز بر عادت؛ ورزش تدریجی',
                'video_url' => 'https://elahejavid.ir/wp-content/uploads/2025/09/F4-03.mp4',
                'order' => 6
            ),
            array(
                'name' => 'چاقی بی‌تحرکی',
                'description' => 'کم‌تحرکی مداوم',
                'tips' => 'هشدار ساعت؛ هدف ۶۰۰۰ قدم؛ وقفهٔ کششی',
                'video_url' => 'https://elahejavid.ir/wp-content/uploads/2025/09/F4-05.mp4',
                'order' => 7
            ),
            array(
                'name' => 'چاقی عادتی',
                'description' => 'خوردن خودکار و بدون آگاهی',
                'tips' => 'قانون بشقاب؛ نهار بدون موبایل؛ ساعت ثابت',
                'video_url' => '',
                'order' => 8
            ),
            array(
                'name' => 'چاقی ترکیبی',
                'description' => 'چندعاملی',
                'tips' => 'تغییر کوچک در هر عامل؛ پیگیری وزن/خواب؛ برنامهٔ خرید هفتگی',
                'video_url' => '',
                'order' => 9
            )
        );
        
        // درج گروه‌ها
        foreach ($groups as $group) {
            $wpdb->insert(
                $wpdb->prefix . 'oa_groups',
                array(
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'tips' => $group['tips'],
                    'video_url' => $group['video_url'],
                    'display_order' => $group['order']
                )
            );
        }
        
        // سوالات و گزینه‌ها
        $this->insert_questions_and_options();
    }
    
    private function insert_questions_and_options() {
        global $wpdb;
        
        // سوالات گروه 1: چاقی هورمونی
        $group1_questions = array(
            array(
                'question' => 'آیا در ناحیه شکم و پهلو بیشتر از سایر نقاط بدن دچار تجمع چربی هستید؟',
                'options' => array(
                    array('text' => 'خیر، چاقی من در کل بدن پخش است', 'score' => 0),
                    array('text' => 'کمی بیشتر در شکم و پهلو', 'score' => 1),
                    array('text' => 'چاقی‌ام عمدتاً در شکم و پهلو است', 'score' => 2),
                    array('text' => 'تقریباً تمام چاقی‌ام در این ناحیه متمرکز شده', 'score' => 3)
                )
            ),
            array(
                'question' => 'کاهش وزن برایتان با رژیم و ورزش چه‌قدر دشوار است؟',
                'options' => array(
                    array('text' => 'خیلی راحت وزن کم می‌کنم', 'score' => 0),
                    array('text' => 'کمی سخت است اما ممکن می‌شود', 'score' => 1),
                    array('text' => 'حتی با رژیم و ورزش، کاهش وزنم بسیار کند است', 'score' => 2),
                    array('text' => 'تقریباً هیچ وزنی کم نمی‌کنم', 'score' => 3)
                )
            ),
            array(
                'question' => 'آیا علائم هورمونی (ریزش مو، خستگی، بی‌نظمی، آکنه، خواب) دارید؟',
                'options' => array(
                    array('text' => 'هیچ', 'score' => 0),
                    array('text' => 'گاهی یک یا دو مورد', 'score' => 1),
                    array('text' => 'چند مورد همزمان', 'score' => 2),
                    array('text' => 'بیشتر علائم به‌شدت', 'score' => 3)
                )
            ),
            array(
                'question' => 'سابقهٔ اختلالات هورمونی در خانواده دارید؟',
                'options' => array(
                    array('text' => 'خیر', 'score' => 0),
                    array('text' => 'یکی از بستگان نزدیک', 'score' => 1),
                    array('text' => 'چند نفر از بستگان نزدیک', 'score' => 2),
                    array('text' => 'بسیار شایع است', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(1, $group1_questions);
        
        // سوالات گروه 2: چاقی استرسی
        $group2_questions = array(
            array(
                'question' => 'هنگام استرس، واکنش به غذا؟',
                'options' => array(
                    array('text' => 'هیچ تغییری نمی‌کنم', 'score' => 0),
                    array('text' => 'کمی گرسنه‌تر می‌شوم', 'score' => 1),
                    array('text' => 'سراغ تنقلات می‌روم', 'score' => 2),
                    array('text' => 'بی‌اختیار می‌خورم', 'score' => 3)
                )
            ),
            array(
                'question' => 'سطح اضطراب روزانه؟',
                'options' => array(
                    array('text' => 'به‌ندرت', 'score' => 0),
                    array('text' => 'گهگاه', 'score' => 1),
                    array('text' => 'بیشتر روزها', 'score' => 2),
                    array('text' => 'تقریباً همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'تجمع چربی کجاست؟',
                'options' => array(
                    array('text' => 'سراسر بدن', 'score' => 0),
                    array('text' => 'ران و باسن', 'score' => 1),
                    array('text' => 'بیشتر شکم', 'score' => 2),
                    array('text' => 'فقط شکم (سفت)', 'score' => 3)
                )
            ),
            array(
                'question' => 'واکنش بدن به رژیم؟',
                'options' => array(
                    array('text' => 'راحت وزن کم می‌کنم', 'score' => 0),
                    array('text' => 'با کمی سختی', 'score' => 1),
                    array('text' => 'سریع استپ می‌کنم', 'score' => 2),
                    array('text' => 'سخت وزن کم می‌کنم مخصوصاً هنگام استرس', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(2, $group2_questions);
        
        // سوالات گروه 3: چاقی متابولیک
        $group3_questions = array(
            array(
                'question' => 'در چند ماه اخیر بدون تغییر رژیم چقدر وزن اضافه کردید؟',
                'options' => array(
                    array('text' => 'هیچ', 'score' => 0),
                    array('text' => '۱–۲ کیلو', 'score' => 1),
                    array('text' => '۳–۵ کیلو', 'score' => 2),
                    array('text' => 'بیش از ۵ کیلو', 'score' => 3)
                )
            ),
            array(
                'question' => 'میزان انرژی روزانه؟',
                'options' => array(
                    array('text' => 'طبیعی', 'score' => 0),
                    array('text' => 'گاهی خستگی', 'score' => 1),
                    array('text' => 'اغلب خسته', 'score' => 2),
                    array('text' => 'همیشه خواب‌آلود', 'score' => 3)
                )
            ),
            array(
                'question' => 'آزمایش تیروئید؟',
                'options' => array(
                    array('text' => 'نه، مشکلی ندارم', 'score' => 0),
                    array('text' => 'مشکوک ولی نرمال', 'score' => 1),
                    array('text' => 'اختلال خفیف/متوسط', 'score' => 2),
                    array('text' => 'اختلال شدید یا تحت درمان', 'score' => 3)
                )
            ),
            array(
                'question' => 'کاهش وزن با رژیم چقدر دشوار است؟',
                'options' => array(
                    array('text' => 'راحت', 'score' => 0),
                    array('text' => 'کمی سخت', 'score' => 1),
                    array('text' => 'بسیار سخت', 'score' => 2),
                    array('text' => 'تقریباً غیرممکن', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(3, $group3_questions);
        
        // سوالات گروه 4: چاقی احساسی
        $group4_questions = array(
            array(
                'question' => 'هنگام ناراحتی، تمایل به غذا؟',
                'options' => array(
                    array('text' => 'اصلاً', 'score' => 0),
                    array('text' => 'کمی بیشتر', 'score' => 1),
                    array('text' => 'سراغ غذاهای خاص', 'score' => 2),
                    array('text' => 'تقریباً همیشه پرخوری', 'score' => 3)
                )
            ),
            array(
                'question' => 'در خستگی یا استرس میل به خوراکی خاص؟',
                'options' => array(
                    array('text' => 'هیچ', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب زیاد', 'score' => 2),
                    array('text' => 'همیشه مقاومت سخت', 'score' => 3)
                )
            ),
            array(
                'question' => 'بعد از پرخوری احساسی؟',
                'options' => array(
                    array('text' => 'عادی', 'score' => 0),
                    array('text' => 'پشیمانی کم', 'score' => 1),
                    array('text' => 'سنگینی شدید', 'score' => 2),
                    array('text' => 'تصمیم رژیم سخت و شکست', 'score' => 3)
                )
            ),
            array(
                'question' => 'آیا مطمئنید گرسنگی واقعی دارید؟',
                'options' => array(
                    array('text' => 'همیشه', 'score' => 0),
                    array('text' => 'بیشتر اوقات', 'score' => 1),
                    array('text' => 'اغلب اشتها احساسی است', 'score' => 2),
                    array('text' => 'همیشه اشتباه تشخیص می‌دهم', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(4, $group4_questions);
        
        // سوالات گروه 5: چاقی ژنتیکی
        $group5_questions = array(
            array(
                'question' => 'از چه سنی اضافه‌وزن شروع شد؟',
                'options' => array(
                    array('text' => 'بزرگسالی', 'score' => 0),
                    array('text' => 'نوجوانی', 'score' => 1),
                    array('text' => 'کودکی', 'score' => 2),
                    array('text' => 'کودکی شدید و تداوم', 'score' => 3)
                )
            ),
            array(
                'question' => 'سابقه خانوادگی؟',
                'options' => array(
                    array('text' => 'خیر', 'score' => 0),
                    array('text' => 'یک نفر', 'score' => 1),
                    array('text' => 'چند نفر', 'score' => 2),
                    array('text' => 'بیشتر خانواده', 'score' => 3)
                )
            ),
            array(
                'question' => 'دشواری کاهش وزن؟',
                'options' => array(
                    array('text' => 'راحت', 'score' => 0),
                    array('text' => 'کمی سخت', 'score' => 1),
                    array('text' => 'بسیار سخت', 'score' => 2),
                    array('text' => 'غیرممکن', 'score' => 3)
                )
            ),
            array(
                'question' => 'کندی متابولیسم؟',
                'options' => array(
                    array('text' => 'طبیعی', 'score' => 0),
                    array('text' => 'کمی کمتر', 'score' => 1),
                    array('text' => 'اغلب کند', 'score' => 2),
                    array('text' => 'همیشه کند', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(5, $group5_questions);
        
        // سوالات گروه 6: چاقی یویویی
        $group6_questions = array(
            array(
                'question' => 'چند بار کاهش و بازگشت وزن؟',
                'options' => array(
                    array('text' => 'هیچ', 'score' => 0),
                    array('text' => 'یک بار', 'score' => 1),
                    array('text' => '۲–۳ بار', 'score' => 2),
                    array('text' => 'بیش از ۳ بار', 'score' => 3)
                )
            ),
            array(
                'question' => 'علت اضافه‌وزن فعلی؟',
                'options' => array(
                    array('text' => 'از بچگی چاق', 'score' => 0),
                    array('text' => 'تغییر سبک زندگی', 'score' => 1),
                    array('text' => 'بارداری/دارو/بیماری', 'score' => 2),
                    array('text' => 'رژیم‌های متعدد', 'score' => 3)
                )
            ),
            array(
                'question' => 'بعد از کاهش وزن چقدر زود برمی‌گردد؟',
                'options' => array(
                    array('text' => 'برنگشته', 'score' => 0),
                    array('text' => 'چند ماه', 'score' => 1),
                    array('text' => 'چند هفته', 'score' => 2),
                    array('text' => 'خیلی زود', 'score' => 3)
                )
            ),
            array(
                'question' => 'احساس هنگام بازگشت وزن؟',
                'options' => array(
                    array('text' => 'بی‌اهمیت', 'score' => 0),
                    array('text' => 'ناراحت ولی ادامه', 'score' => 1),
                    array('text' => 'ناامیدی', 'score' => 2),
                    array('text' => 'رها کردن', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(6, $group6_questions);
        
        // سوالات گروه 7: چاقی بی‌تحرکی
        $group7_questions = array(
            array(
                'question' => 'فعالیت روزانه؟',
                'options' => array(
                    array('text' => 'بیش از ۱ ساعت', 'score' => 0),
                    array('text' => 'حدود ۳۰ دقیقه', 'score' => 1),
                    array('text' => 'کمتر از ۳۰ دقیقه', 'score' => 2),
                    array('text' => 'تقریباً هیچ', 'score' => 3)
                )
            ),
            array(
                'question' => 'زمان نشستن روزانه؟',
                'options' => array(
                    array('text' => '<۲ ساعت', 'score' => 0),
                    array('text' => '۲–۴ ساعت', 'score' => 1),
                    array('text' => '۴–۶ ساعت', 'score' => 2),
                    array('text' => '>۶ ساعت', 'score' => 3)
                )
            ),
            array(
                'question' => 'تأثیر کم‌تحرکی؟',
                'options' => array(
                    array('text' => 'هیچ', 'score' => 0),
                    array('text' => 'کم', 'score' => 1),
                    array('text' => 'زیاد', 'score' => 2),
                    array('text' => 'عامل اصلی', 'score' => 3)
                )
            ),
            array(
                'question' => 'فعالیت روزانه (تکرار)؟',
                'options' => array(
                    array('text' => 'بیش از ۱ ساعت', 'score' => 0),
                    array('text' => 'حدود ۳۰ دقیقه', 'score' => 1),
                    array('text' => 'کمتر از ۳۰ دقیقه', 'score' => 2),
                    array('text' => 'تقریباً هیچ', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(7, $group7_questions);
        
        // سوالات گروه 8: چاقی عادتی
        $group8_questions = array(
            array(
                'question' => 'غذا بدون گرسنگی؟',
                'options' => array(
                    array('text' => 'اصلاً', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب', 'score' => 2),
                    array('text' => 'همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'غذا هنگام تلویزیون/موبایل؟',
                'options' => array(
                    array('text' => 'اصلاً', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب', 'score' => 2),
                    array('text' => 'همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'سرعت غذا خوردن؟',
                'options' => array(
                    array('text' => 'خیلی آرام', 'score' => 0),
                    array('text' => 'معمولی', 'score' => 1),
                    array('text' => 'کمی سریع', 'score' => 2),
                    array('text' => 'خیلی سریع', 'score' => 3)
                )
            ),
            array(
                'question' => 'نظم وعده‌ها؟',
                'options' => array(
                    array('text' => 'منظم', 'score' => 0),
                    array('text' => 'گاهی نامنظم', 'score' => 1),
                    array('text' => 'اغلب نامنظم', 'score' => 2),
                    array('text' => 'همیشه نامنظم', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(8, $group8_questions);
        
        // سوالات گروه 9: چاقی ترکیبی
        $group9_questions = array(
            array(
                'question' => 'بیشتر از نیاز روزانه غذا می‌خورید؟',
                'options' => array(
                    array('text' => 'اصلاً', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب', 'score' => 2),
                    array('text' => 'همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'تمایل به فست‌فود/شیرینی؟',
                'options' => array(
                    array('text' => 'خیلی کم', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب', 'score' => 2),
                    array('text' => 'همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'علت اصلی چاقی؟',
                'options' => array(
                    array('text' => 'فقط کم‌تحرکی', 'score' => 0),
                    array('text' => 'فقط عادات غذایی', 'score' => 1),
                    array('text' => 'ترکیب دو عامل', 'score' => 2),
                    array('text' => 'ترکیب چند عامل', 'score' => 3)
                )
            ),
            array(
                'question' => 'دشواری کاهش وزن؟',
                'options' => array(
                    array('text' => 'راحت', 'score' => 0),
                    array('text' => 'کمی سخت', 'score' => 1),
                    array('text' => 'سخت', 'score' => 2),
                    array('text' => 'غیرممکن', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(9, $group9_questions);
    }
    
    private function insert_group_questions($group_id, $questions) {
        global $wpdb;
        
        foreach ($questions as $index => $question_data) {
            // درج سوال
            $wpdb->insert(
                $wpdb->prefix . 'oa_questions',
                array(
                    'group_id' => $group_id,
                    'question_text' => $question_data['question'],
                    'display_order' => $index + 1
                )
            );
            
            $question_id = $wpdb->insert_id;
            
            // درج گزینه‌ها
            foreach ($question_data['options'] as $opt_index => $option) {
                $wpdb->insert(
                    $wpdb->prefix . 'oa_options',
                    array(
                        'question_id' => $question_id,
                        'option_text' => $option['text'],
                        'score' => $option['score'],
                        'display_order' => $opt_index + 1
                    )
                );
            }
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'تست تشخیص چاقی',
            'تست تشخیص چاقی',
            'manage_options',
            'obesity-assessment',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );
    }
    
    public function admin_page() {
        include OA_PLUGIN_PATH . 'admin/dashboard.php';
    }
    
    public function quiz_shortcode($atts) {
        ob_start();
        include OA_PLUGIN_PATH . 'templates/quiz-form.php';
        return ob_get_clean();
    }
    
    public function add_menu_item($items, $args) {
        if ($args->theme_location == 'primary') {
            $result_page_url = home_url('/oa-result/');
            $items .= '<li class="menu-item"><a href="' . $result_page_url . '">ویدئوی دستهٔ شما</a></li>';
        }
        return $items;
    }
    
    public function add_rewrite_rules() {
        add_rewrite_rule('^oa-result/?$', 'index.php?oa_result=1', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'oa_result';
        return $vars;
    }
    
    public function template_redirect() {
        if (get_query_var('oa_result')) {
            include OA_PLUGIN_PATH . 'templates/result-page.php';
            exit;
        }
    }
    
    public function submit_quiz() {
        check_ajax_referer('oa_quiz_nonce', 'nonce');
        
        global $wpdb;
        
        $answers = $_POST['answers'];
        $group_scores = array();
        
        // محاسبه امتیاز هر گروه
        for ($group_id = 1; $group_id <= 9; $group_id++) {
            $group_score = 0;
            for ($q = 1; $q <= 4; $q++) {
                $question_index = ($group_id - 1) * 4 + $q - 1;
                if (isset($answers[$question_index])) {
                    $group_score += intval($answers[$question_index]);
                }
            }
            $group_scores[$group_id] = $group_score;
        }
        
        // پیدا کردن گروه‌های برنده
        $winning_groups = array();
        $max_score = max($group_scores);
        
        if ($max_score == 12) {
            // گروه‌هایی که امتیاز کامل دارند
            foreach ($group_scores as $group_id => $score) {
                if ($score == 12) {
                    $winning_groups[] = $group_id;
                }
            }
        } else {
            // گروه‌هایی که بیشترین امتیاز را دارند
            foreach ($group_scores as $group_id => $score) {
                if ($score == $max_score) {
                    $winning_groups[] = $group_id;
                }
            }
        }
        
        // ذخیره نتیجه
        $session_id = session_id();
        if (empty($session_id)) {
            session_start();
            $session_id = session_id();
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'oa_results',
            array(
                'user_id' => get_current_user_id(),
                'session_id' => $session_id,
                'group_scores' => json_encode($group_scores),
                'winning_groups' => json_encode($winning_groups)
            )
        );
        
        // ذخیره در session
        $_SESSION['oa_result'] = array(
            'group_scores' => $group_scores,
            'winning_groups' => $winning_groups
        );
        
        wp_send_json_success(array(
            'redirect_url' => home_url('/oa-result/')
        ));
    }
    
    // تست AJAX
    public function test_ajax() {
        wp_send_json_success(array(
            'message' => 'AJAX کار می‌کند!',
            'user_id' => get_current_user_id(),
            'is_admin' => current_user_can('manage_options'),
            'nonce' => wp_create_nonce('oa_admin_nonce')
        ));
    }
}

// راه‌اندازی افزونه
new ObesityAssessment();
