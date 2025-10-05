<?php
/**
 * Plugin Name: تست تشخیص نوع چاقی
 * Plugin URI: https://elahejavid.ir
 * Description: افزونه تست تشخیص نوع چاقی با 9 گروه مختلف و مدیریت داینامیک سوالات
 * Version: 1.0.19
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
        add_shortcode('obesity_assessment', array($this, 'quiz_shortcode'));
        add_shortcode('oa_quiz_all', array($this, 'quiz_all_shortcode'));
        add_shortcode('oa_flush_rules', array($this, 'flush_rules_shortcode'));
        
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
        $this->add_rewrite_rules();
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
                'question' => 'آیا علائم دیگری که می‌تواند به اختلالات هورمونی مربوط باشد دارید؟ (مثلاً ریزش مو، خستگی دائمی، بی‌نظمی قاعدگی، آکنه یا مشکلات خواب)',
                'options' => array(
                    array('text' => 'هیچ‌کدام از این علائم را ندارم', 'score' => 0),
                    array('text' => 'گاهی یک یا دو مورد را تجربه می‌کنم', 'score' => 1),
                    array('text' => 'چند مورد از این علائم را همزمان دارم', 'score' => 2),
                    array('text' => 'بیشتر این علائم را به‌شدت دارم', 'score' => 3)
                )
            ),
            array(
                'question' => 'آیا در خانواده‌تان سابقه اختلالات هورمونی (سندرم تخمدان پلی کیستیک، PCOS، دیابت یا یائسگی زودرس مقاومت ب انسولین) وجود دارد؟',
                'options' => array(
                    array('text' => 'خیر، هیچ‌کس چنین مشکلی ندارد', 'score' => 0),
                    array('text' => 'یکی از بستگان نزدیک', 'score' => 1),
                    array('text' => 'چند نفر از بستگان نزدیک', 'score' => 2),
                    array('text' => 'این مشکل در خانواده بسیار شایع است', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(1, $group1_questions);
        
        // سوالات گروه 2: چاقی استرسی
        $group2_questions = array(
            array(
                'question' => 'وقتی دچار استرس می‌شوید، معمولاً چه واکنشی نسبت به غذا خوردن دارید؟',
                'options' => array(
                    array('text' => 'هیچ تغییری نمی‌کنم و اشتهایم طبیعی است', 'score' => 0),
                    array('text' => 'کمی احساس گرسنگی بیشتری می‌کنم', 'score' => 1),
                    array('text' => 'اغلب به سراغ تنقلات و غذاهای شیرین یا شور می‌روم', 'score' => 2),
                    array('text' => 'معمولاً بی‌اختیار و بدون کنترل شروع به خوردن می‌کنم', 'score' => 3)
                )
            ),
            array(
                'question' => 'در طول روز، سطح اضطراب و نگرانی شما چگونه است؟',
                'options' => array(
                    array('text' => 'به‌ندرت دچار اضطراب می‌شوم', 'score' => 0),
                    array('text' => 'گهگاه نگرانی‌های کوچک دارم', 'score' => 1),
                    array('text' => 'بیشتر روزها احساس اضطراب یا تنش دارم', 'score' => 2),
                    array('text' => 'تقریباً همیشه درگیر اضطراب، نگرانی یا استرس هستم', 'score' => 3)
                )
            ),
            array(
                'question' => 'تجمع چربی در بدن شما بیشتر در کدام ناحیه است؟',
                'options' => array(
                    array('text' => 'به‌طور یکنواخت در سراسر بدنم', 'score' => 0),
                    array('text' => 'عمدتاً در ران‌ها و باسن', 'score' => 1),
                    array('text' => 'بیشتر در ناحیه شکم', 'score' => 2),
                    array('text' => 'تقریباً فقط شکم، مخصوصاً شکم سفت و برجسته', 'score' => 3)
                )
            ),
            array(
                'question' => 'واکنش بدن شما به رژیم‌های غذایی قبلی چگونه بوده است؟',
                'options' => array(
                    array('text' => 'به‌راحتی وزن کم می‌کنم', 'score' => 0),
                    array('text' => 'کاهش وزن دارم اما با کمی سختی', 'score' => 1),
                    array('text' => 'کاهش وزن دارم اما سریع دچار استپ وزنی می‌شوم', 'score' => 2),
                    array('text' => 'به‌سختی وزن کم می‌کنم، مخصوصاً وقتی تحت استرس هستم', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(2, $group2_questions);
        
        // سوالات گروه 3: چاقی متابولیک
        $group3_questions = array(
            array(
                'question' => 'در چند ماه اخیر، بدون تغییر قابل توجه در رژیم غذایی یا فعالیت، چه مقدار وزن اضافه کرده‌اید؟',
                'options' => array(
                    array('text' => 'هیچ‌گونه افزایشی نداشته‌ام', 'score' => 0),
                    array('text' => 'کمی (۱–۲ کیلو)', 'score' => 1),
                    array('text' => 'متوسط (۳–۵ کیلو)', 'score' => 2),
                    array('text' => 'زیاد (بیش از ۵ کیلو)', 'score' => 3)
                )
            ),
            array(
                'question' => 'در طول روز چه میزان احساس خستگی یا کمبود انرژی دارید؟',
                'options' => array(
                    array('text' => 'انرژی کاملاً طبیعی و فعال هستم', 'score' => 0),
                    array('text' => 'گاهی احساس خستگی دارم', 'score' => 1),
                    array('text' => 'اغلب اوقات خسته هستم', 'score' => 2),
                    array('text' => 'تقریباً همیشه کم‌انرژی و خواب‌آلودم', 'score' => 3)
                )
            ),
            array(
                'question' => 'آیا تاکنون آزمایش تیروئید داده‌اید یا پزشک شما اختلال تیروئید را تشخیص داده است؟',
                'options' => array(
                    array('text' => 'خیر، هیچ وقت آزمایش نداده‌ام و مشکلی ندارم', 'score' => 0),
                    array('text' => 'کمی مشکوک بودم ولی آزمایشم نرمال بود', 'score' => 1),
                    array('text' => 'بله، اختلال خفیف یا متوسط تشخیص داده شده', 'score' => 2),
                    array('text' => 'بله، اختلال تیروئید شدید یا تحت درمان هستم', 'score' => 3)
                )
            ),
            array(
                'question' => 'وقتی رژیم یا ورزش می‌کنید، کاهش وزن چقدر برایتان دشوار است؟',
                'options' => array(
                    array('text' => 'به راحتی وزن کم می‌کنم', 'score' => 0),
                    array('text' => 'کمی سخت است ولی ممکن', 'score' => 1),
                    array('text' => 'بسیار دشوار است و کم کاهش می‌یابد', 'score' => 2),
                    array('text' => 'تقریباً هیچ کاهش وزنی ندارم', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(3, $group3_questions);
        
        // سوالات گروه 4: چاقی احساسی
        $group4_questions = array(
            array(
                'question' => 'وقتی ناراحت، عصبی یا تحت فشار هستید، چقدر به غذا خوردن تمایل پیدا می‌کنید؟',
                'options' => array(
                    array('text' => 'اصلاً تغییری نمی‌کنه', 'score' => 0),
                    array('text' => 'کمی بیشتر از حالت عادی می‌خورم', 'score' => 1),
                    array('text' => 'معمولاً سراغ غذاهای خاصی می‌روم', 'score' => 2),
                    array('text' => 'تقریباً همیشه در این مواقع پرخوری می‌کنم', 'score' => 3)
                )
            ),
            array(
                'question' => 'در زمان خستگی یا استرس، چقدر میل شدیدی به خوردن خوراکی‌های خاص (مثل شیرینی، شکلات، فست‌فود یا نوشابه) پیدا می‌کنید؟',
                'options' => array(
                    array('text' => 'هیچ تمایلی ندارم', 'score' => 0),
                    array('text' => 'گاهی کمی هوس می‌کنم', 'score' => 1),
                    array('text' => 'اغلب اوقات هوس شدید دارم', 'score' => 2),
                    array('text' => 'تقریباً همیشه نمی‌توانم در برابر این خوراکی‌ها مقاومت کنم', 'score' => 3)
                )
            ),
            array(
                'question' => 'بعد از پرخوری ناشی از احساسات، چه حسی دارید؟',
                'options' => array(
                    array('text' => 'احساس خاصی ندارم، عادیه', 'score' => 0),
                    array('text' => 'کمی پشیمانی یا عذاب وجدان', 'score' => 1),
                    array('text' => 'احساس سنگینی و ناراحتی شدید', 'score' => 2),
                    array('text' => 'علاوه بر پشیمانی، تصمیم می‌گیرم رژیم سختی بگیرم اما شکست می‌خورم', 'score' => 3)
                )
            ),
            array(
                'question' => 'وقتی به سمت غذا می‌روید، چقدر مطمئن هستید که واقعاً گرسنه‌اید و نه صرفاً برای آرام شدن غذا می‌خورید؟',
                'options' => array(
                    array('text' => 'همیشه مطمئنم و فقط در زمان گرسنگی می‌خورم', 'score' => 0),
                    array('text' => 'بیشتر اوقات متوجه تفاوت گرسنگی و هوس می‌شوم', 'score' => 1),
                    array('text' => 'اغلب اوقات اشتهایم از احساسات می‌آید نه گرسنگی واقعی', 'score' => 2),
                    array('text' => 'تقریباً همیشه نمی‌توانم این دو را از هم تشخیص دهم', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(4, $group4_questions);
        
        // سوالات گروه 5: چاقی ژنتیکی
        $group5_questions = array(
            array(
                'question' => 'از چه سنی اضافه وزن یا چاقی برایتان شروع شد؟',
                'options' => array(
                    array('text' => 'در بزرگسالی', 'score' => 0),
                    array('text' => 'در نوجوانی', 'score' => 1),
                    array('text' => 'از کودکی', 'score' => 2),
                    array('text' => 'از کودکی با شدت بالا و ادامه تا بزرگسالی', 'score' => 3)
                )
            ),
            array(
                'question' => 'آیا در خانواده شما (والدین، خواهر/برادر) چاقی یا اضافه وزن شدید وجود دارد؟',
                'options' => array(
                    array('text' => 'خیر، هیچ‌کس چاق نیست', 'score' => 0),
                    array('text' => 'یکی از اعضای نزدیک خانواده', 'score' => 1),
                    array('text' => 'چند نفر از اعضای نزدیک', 'score' => 2),
                    array('text' => 'بیشتر خانواده چاق یا اضافه وزن دارند', 'score' => 3)
                )
            ),
            array(
                'question' => 'چقدر کاهش وزن برایتان دشوار است؟',
                'options' => array(
                    array('text' => 'به راحتی وزن کم می‌کنم', 'score' => 0),
                    array('text' => 'کمی سخت است', 'score' => 1),
                    array('text' => 'بسیار سخت است و کاهش وزن کند است', 'score' => 2),
                    array('text' => 'تقریباً غیرممکن است', 'score' => 3)
                )
            ),
            array(
                'question' => 'آیا احساس می‌کنید متابولیسم یا سوخت و ساز بدن شما کند است و انرژی کمتری نسبت به دیگران دارید؟',
                'options' => array(
                    array('text' => 'کاملاً طبیعی و فعال هستم', 'score' => 0),
                    array('text' => 'کمی کمتر از دیگران', 'score' => 1),
                    array('text' => 'اغلب احساس کم‌انرژی و کندی دارم', 'score' => 2),
                    array('text' => 'تقریباً همیشه بدنم کند است و انرژی پایینی دارم', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(5, $group5_questions);
        
        // سوالات گروه 6: چاقی یویویی
        $group6_questions = array(
            array(
                'question' => 'چند بار در طول زندگی‌تان کاهش وزن زیاد (بیش از ۵ کیلوگرم) و بازگشت آن را تجربه کرده‌اید؟',
                'options' => array(
                    array('text' => 'هیچ‌وقت', 'score' => 0),
                    array('text' => 'یک بار', 'score' => 1),
                    array('text' => 'دو تا سه بار', 'score' => 2),
                    array('text' => 'بیش از سه بار', 'score' => 3)
                )
            ),
            array(
                'question' => 'دلیل شروع اضافه‌وزن فعلی شما چه بوده است؟',
                'options' => array(
                    array('text' => 'از بچگی همیشه چاق بودم', 'score' => 0),
                    array('text' => 'تغییر سبک زندگی یا تغذیه', 'score' => 1),
                    array('text' => 'بارداری، دارو یا بیماری خاص', 'score' => 2),
                    array('text' => 'رژیم‌های متعدد و برگشت وزن', 'score' => 3)
                )
            ),
            array(
                'question' => 'هنگام کاهش وزن، بعد از چند وقت به استاپ وزنی یا برگشت وزن می‌رسید؟',
                'options' => array(
                    array('text' => 'استاپ یا برگشت نداشتم', 'score' => 0),
                    array('text' => 'بعد از چند ماه', 'score' => 1),
                    array('text' => 'بعد از چند هفته', 'score' => 2),
                    array('text' => 'خیلی زود یا بلافاصله', 'score' => 3)
                )
            ),
            array(
                'question' => 'وقتی وزنتان برمی‌گردد، احساس شما چطور است؟',
                'options' => array(
                    array('text' => 'خیلی برام مهم نیست', 'score' => 0),
                    array('text' => 'ناراحت می‌شم ولی ادامه می‌دم', 'score' => 1),
                    array('text' => 'احساس ناامیدی می‌کنم', 'score' => 2),
                    array('text' => 'کلاً بی‌خیال رژیم یا ورزش می‌شم', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(6, $group6_questions);
        
        // سوالات گروه 7: چاقی بی‌تحرکی
        $group7_questions = array(
            array(
                'question' => 'در طول روز، چقدر زمان شما صرف فعالیت فیزیکی (پیاده‌روی، ورزش، کار بدنی) می‌شود؟',
                'options' => array(
                    array('text' => 'بیش از ۱ ساعت فعال هستم', 'score' => 0),
                    array('text' => 'حدود ۳۰ دقیقه فعالیت دارم', 'score' => 1),
                    array('text' => 'کمتر از ۳۰ دقیقه فعال هستم', 'score' => 2),
                    array('text' => 'تقریباً هیچ فعالیت بدنی ندارم', 'score' => 3)
                )
            ),
            array(
                'question' => 'در روز چه مقدار وقت خود را به صورت نشسته می‌گذرانید؟ (کار، تلویزیون، موبایل)',
                'options' => array(
                    array('text' => 'کمتر از ۲ ساعت', 'score' => 0),
                    array('text' => '۲ تا ۴ ساعت', 'score' => 1),
                    array('text' => '۴ تا ۶ ساعت', 'score' => 2),
                    array('text' => 'بیش از ۶ ساعت', 'score' => 3)
                )
            ),
            array(
                'question' => 'چقدر احساس می‌کنید سبک زندگی کم‌تحرک بر افزایش وزن شما اثر گذاشته است؟',
                'options' => array(
                    array('text' => 'اصلاً تأثیری نداشته', 'score' => 0),
                    array('text' => 'کمی تأثیر داشته', 'score' => 1),
                    array('text' => 'تأثیر قابل توجهی داشته', 'score' => 2),
                    array('text' => 'عامل اصلی افزایش وزن من است', 'score' => 3)
                )
            ),
            array(
                'question' => 'آیا احساس می‌کنید که کم‌تحرکی باعث کاهش انرژی و انگیزه شما برای فعالیت‌های بدنی شده است؟',
                'options' => array(
                    array('text' => 'اصلاً، انرژی و انگیزه کافی دارم', 'score' => 0),
                    array('text' => 'کمی احساس کم‌انرژی می‌کنم', 'score' => 1),
                    array('text' => 'اغلب احساس کم‌انرژی و بی‌انگیزه‌ام', 'score' => 2),
                    array('text' => 'همیشه احساس کم‌انرژی و بی‌انگیزه‌ام', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(7, $group7_questions);
        
        // سوالات گروه 8: چاقی عادتی
        $group8_questions = array(
            array(
                'question' => 'چقدر اغلب بدون اینکه واقعاً گرسنه باشید، غذا یا تنقلات می‌خورید؟',
                'options' => array(
                    array('text' => 'اصلاً چنین کاری نمی‌کنم', 'score' => 0),
                    array('text' => 'گاهی اوقات', 'score' => 1),
                    array('text' => 'اغلب اوقات', 'score' => 2),
                    array('text' => 'تقریباً همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'چقدر هنگام تماشای تلویزیون، موبایل یا کار با کامپیوتر غذا می‌خورید؟',
                'options' => array(
                    array('text' => 'اصلاً', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب', 'score' => 2),
                    array('text' => 'تقریباً همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'معمولاً چه سرعتی غذا می‌خورید؟',
                'options' => array(
                    array('text' => 'خیلی آرام و با تمرکز', 'score' => 0),
                    array('text' => 'معمولی، نه خیلی سریع', 'score' => 1),
                    array('text' => 'کمی سریع و بدون تمرکز', 'score' => 2),
                    array('text' => 'خیلی سریع و بدون توجه به حجم غذا', 'score' => 3)
                )
            ),
            array(
                'question' => 'چقدر وعده‌های غذایی خود را دیر یا نامنظم مصرف می‌کنید؟',
                'options' => array(
                    array('text' => 'همیشه وعده‌ها را سر وقت و منظم می‌خورم', 'score' => 0),
                    array('text' => 'گاهی نامنظم می‌خورم', 'score' => 1),
                    array('text' => 'اغلب وعده‌هایم نامنظم است', 'score' => 2),
                    array('text' => 'تقریباً همیشه وعده‌هایم نامنظم و دیر است', 'score' => 3)
                )
            )
        );
        
        $this->insert_group_questions(8, $group8_questions);
        
        // سوالات گروه 9: چاقی ترکیبی
        $group9_questions = array(
            array(
                'question' => 'چقدر اغلب بیش از نیاز روزانه خود غذا می‌خورید؟',
                'options' => array(
                    array('text' => 'اصلاً', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب', 'score' => 2),
                    array('text' => 'تقریباً همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'چقدر تمایل دارید غذاهای پرچرب، شیرین یا فست‌فود مصرف کنید؟',
                'options' => array(
                    array('text' => 'خیلی کم یا اصلاً', 'score' => 0),
                    array('text' => 'گاهی', 'score' => 1),
                    array('text' => 'اغلب', 'score' => 2),
                    array('text' => 'تقریباً همیشه', 'score' => 3)
                )
            ),
            array(
                'question' => 'به نظر شما علت اصلی افزایش وزن شما چیست؟',
                'options' => array(
                    array('text' => 'فقط سبک زندگی کم‌تحرک', 'score' => 0),
                    array('text' => 'فقط عادات غذایی نادرست', 'score' => 1),
                    array('text' => 'ترکیبی از دو عامل (فعالیت کم + عادات غذایی)', 'score' => 2),
                    array('text' => 'ترکیبی از چند عامل (فعالیت کم، عادات غذایی، استرس، هورمون و غیره)', 'score' => 3)
                )
            ),
            array(
                'question' => 'چقدر کاهش وزن برایتان دشوار است؟',
                'options' => array(
                    array('text' => 'راحت وزن کم می‌کنم', 'score' => 0),
                    array('text' => 'کمی سخت است', 'score' => 1),
                    array('text' => 'بسیار سخت است', 'score' => 2),
                    array('text' => 'تقریباً غیرممکن است', 'score' => 3)
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
    
    public function quiz_all_shortcode($atts) {
        try {
            ob_start();
            include OA_PLUGIN_PATH . 'templates/quiz-form-all.php';
            $content = ob_get_clean();
            return $content;
        } catch (Exception $e) {
            return '<p>خطا در بارگذاری فرم تست. لطفاً دوباره تلاش کنید.</p>';
        }
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
        add_rewrite_rule('^oa-result/([^/]+)/?$', 'index.php?oa_result=1&oa_result_id=$matches[1]', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'oa_result';
        $vars[] = 'oa_result_id';
        return $vars;
    }
    
    public function template_redirect() {
        if (get_query_var('oa_result')) {
            // پردازش فرم اگر از فرم همه سوالات ارسال شده
            if (isset($_POST['oa_submit_all']) && $_POST['oa_submit_all'] == '1') {
                $this->process_all_questions_form();
            }
            
            // بررسی وجود session
            if (!session_id()) {
                session_start();
            }
            
            if (!isset($_SESSION['oa_result'])) {
                // اگر session وجود ندارد، به صفحه اصلی هدایت کن
                wp_redirect(home_url('/'));
                exit;
            }
            
            include OA_PLUGIN_PATH . 'templates/result-page.php';
            exit;
        }
    }
    
    private function process_all_questions_form() {
        check_ajax_referer('oa_quiz_nonce', 'nonce');
        
        global $wpdb;
        
        $answers = $_POST['answers'];
        $group_scores = array();
        
        // محاسبه امتیاز هر گروه
        for ($group_id = 1; $group_id <= 9; $group_id++) {
            $group_score = 0;
            
            // دریافت سوالات این گروه
            $group_questions = $wpdb->get_results($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}oa_questions 
                WHERE group_id = %d 
                ORDER BY display_order
            ", $group_id));
            
            foreach ($group_questions as $question) {
                if (isset($answers[$question->id])) {
                    $option_index = intval($answers[$question->id]);
                    // دریافت امتیاز گزینه انتخاب شده
                    $option_score = $wpdb->get_var($wpdb->prepare("
                        SELECT score FROM {$wpdb->prefix}oa_options 
                        WHERE question_id = %d AND display_order = %d
                    ", $question->id, $option_index + 1));
                    
                    if ($option_score !== null) {
                        $group_score += intval($option_score);
                    }
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
    }
    
    public function submit_quiz() {
        check_ajax_referer('oa_quiz_nonce', 'nonce');
        
        global $wpdb;
        
        $answers = $_POST['answers'];
        $group_scores = array();
        
        // محاسبه امتیاز هر گروه
        for ($group_id = 1; $group_id <= 9; $group_id++) {
            $group_score = 0;
            
            // دریافت سوالات این گروه
            $group_questions = $wpdb->get_results($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}oa_questions 
                WHERE group_id = %d 
                ORDER BY display_order
            ", $group_id));
            
            foreach ($group_questions as $question) {
                if (isset($answers[$question->id])) {
                    $option_index = intval($answers[$question->id]);
                    // دریافت امتیاز گزینه انتخاب شده
                    $option_score = $wpdb->get_var($wpdb->prepare("
                        SELECT score FROM {$wpdb->prefix}oa_options 
                        WHERE question_id = %d AND display_order = %d
                    ", $question->id, $option_index + 1));
                    
                    if ($option_score !== null) {
                        $group_score += intval($option_score);
                    }
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
    
    // تابع برای flush کردن rewrite rules
    public function flush_rewrite_rules_now() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        return true;
    }
    
    // شورت‌کد برای flush کردن rewrite rules
    public function flush_rules_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>شما دسترسی لازم را ندارید.</p>';
        }
        
        $this->flush_rewrite_rules_now();
        return '<p style="color: green;">Rewrite rules با موفقیت بازنشانی شد!</p>';
    }
}

// راه‌اندازی افزونه
new ObesityAssessment();
