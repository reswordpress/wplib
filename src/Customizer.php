<?php
/**
 * Author: Vitali Lupu <vitaliix@gmail.com>
 * Date: 3/5/18
 * Time: 9:02 PM
 * Docs: https://developer.wordpress.org/themes/customize-api/
 */

namespace wp;

use WP_Customize_Cropped_Image_Control;
use WP_Customize_Manager;

class Customizer
{
    //CONST
    const SITE_WATERMARK = 'themeSiteWatermark';
    const SITE_EMAIL = 'themeSiteEmail';
    const SITE_PHONES = 'themeSitePhone';
    const SITE_SKYPE = 'themeSiteSkype';
    const SITE_ADDRESS = 'themeSiteAddress';
    const SITE_REGISTRATION = 'themeSiteRegistration';
    const BACKEND_ACCESS_LEVEL = 'themeSiteAccessLevel';
    const RECAPTCHA_SHOW = 'themeReCaptchaShow';
    const RECAPTCHA_KEY_PUBLIC = 'themeReCaptchaPublicKey';
    const RECAPTCHA_KEY_PRIVATE = 'themeReCaptchaPrivateKey';
    const GOOGLE_ANALYTICS = 'themeGoogleAnalytics';
    const GOOGLE_MAP_API = 'themeGoogleMapApi';
    const AGENTS_EMAIL_CC = 'themeSiteEmailCC';
    const AGENTS_EMAIL_CC_ENABLE = 'themeSiteEmailCCEnabled';

    protected static $siteContacts;
    /**
     * https://developer.apple.com/library/content/featuredarticles/iPhoneURLScheme_Reference/MapLinks/MapLinks.html
     * for maps: "http://maps.apple.com/?q=" - apple / google: "https://www.google.com/maps/?q=" / map:
     * heateorSssPopup
     */
    protected static $referencePrefixes;
    protected static $settingsIconsFa = [
        self::SITE_ADDRESS => "fa fa-map-marker",
        self::SITE_EMAIL => "fa fa-envelope",
        self::SITE_PHONES => "fa fa-phone",
        self::SITE_SKYPE => "fa fa-skype",
    ];
    /**
     * Register meta settings for widget sidebars.
     * @global \WP_Customize_Manager $wp_customize
     */
    private $widgetAreaScriptData = '';
    //Singleton Instance
    protected static $instance = null;

    public static function i()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    protected function __construct()
    {
        add_action(WPActions::CUSTOMIZER_REGISTER, [$this, 'registerCustomizer']);
        //Remove: Custom CSS Section
//        add_action(WPActions::CUSTOMIZER_REGISTER, [$this, 'removeCssSection'], 15);
    }

    /**
     * Remove the additional CSS section, introduced in 4.7, from the Customizer.
     * @param $wp_customize WP_Customize_Manager
     */
    function removeCssSection($wp_customize)
    {
        $wp_customize->remove_section('custom_css');
    }

    /**
     * Init Customizer
     * @param WP_Customize_Manager $wp_customize
     */
    function registerCustomizer(WP_Customize_Manager $wp_customize)
    {
        Customizer::i($wp_customize);
        add_action(WPActions::ENQUEUE_SCRIPTS_CUSTOMIZER, [$this, 'enqueueScriptsCustomizer']);
        add_action(WPActions::CUSTOMIZER_INIT, [$this, 'enqueueScriptsCustomizerPreview']);
        add_action(WPActions::CUSTOMIZER_AFTER_SAVE, [$this, 'handleActionAfterSave']);
        if (empty($wp_customize->widgets) == false) {
            if (is_admin()) {
                $this->registerSidebarSettings(); //Before Customizer Section Loaded
            } else {
                add_action('wp', [$this, 'registerSidebarSettings'], 100); //After All Customizer Section Loaded
            }
        }
        /** Section: Identity */
        $this->registerSectionIdentity($wp_customize);
        /** Section: Google */
        $this->registerSectionGoogle($wp_customize);
        /** Section: Payments */
        $this->registerSectionPayments($wp_customize);
        /** Section: Default */
        $this->registerCustomizerDefaults($wp_customize);
    }

    function registerSidebarSettings()
    {
        global $wp_customize;
        $widgetAreaOptions = Widget::WIDGET_AREA;
        $widgetAreaControlOptions = Widget::CSS_CLASSES;
        foreach ($wp_customize->sections() as $section) {
            if ($section instanceof \WP_Customize_Sidebar_Section) {
                $widgetAreaId = $section->sidebar_id;
                $settingId = "{$widgetAreaOptions}[{$widgetAreaId}][{$widgetAreaControlOptions}]";
                $setting = $wp_customize->add_setting($settingId, [
                    CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
                    CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
                    CustomizerSettingArgs::STD => '',
                    CustomizerSettingArgs::TRANSPORT => CustomizerTransport::POST_MESSAGE
                ]);
                $wp_customize->add_control($settingId, [
                    CustomizerControlArgs::SECTION => $section->id,
                    CustomizerControlArgs::SIDEBAR_ID => $widgetAreaId,
                    CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
                    CustomizerControlArgs::LABEL => __('CSS Classes'),
                    CustomizerControlArgs::PRIORITY => -1
                ]);


                // Handle previewing of late-created settings.
                if (did_action(WPActions::CUSTOMIZER_INIT)) {
                    $setting->preview();
                }
                $this->widgetAreaScriptData .= "wp.customize('{$settingId}', function (value) {
                    value.bind(function (newValue) {
                        var widgetAreaId = '{$widgetAreaId}';
                        var widgetArea = jQuery(document.getElementById(widgetAreaId));
                        console.log(widgetAreaId,widgetArea);
                        widgetArea.removeClass();
                        widgetArea.addClass('widget-area '+ widgetAreaId + ' ' + newValue);
                    });
                });\n";
            }
        }
        wp_add_inline_script('customizer', $this->widgetAreaScriptData);
    }

    /**
     * Register Section: Identity
     *
     * @param WP_Customize_Manager $wp_customize
     */
    function registerSectionIdentity(WP_Customize_Manager $wp_customize)
    {
        /** Site Watermark */
        $customLogoArgs = get_theme_support(WPOptions::SITE_LOGO);
        $wp_customize->add_setting(self::SITE_WATERMARK);
        $wp_customize->add_control(new WP_Customize_Cropped_Image_Control($wp_customize, self::SITE_WATERMARK, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::LABEL => __('Watermark'),
            'height' => $customLogoArgs[0]['height'],
            'width' => $customLogoArgs[0]['width'],
            'flex_height' => $customLogoArgs[0]['flex-height'],
            'flex_width' => $customLogoArgs[0]['flex-width'],
            'priority' => 9,
            'button_labels' => [
                'select' => __('Select Image'),
                'change' => __('Change Image'),
                'remove' => __('Remove'),
                'default' => __('Default'),
                'placeholder' => __('No image selected'),
                'frame_title' => __('Select Image'),
                'frame_button' => __('Choose image'),
            ],
        ]));
        /** Contact Information */
        $wp_customize->add_setting('headerForCategoryContactInformation');
        $wp_customize->add_control(new CustomizerControlSeparator($wp_customize, 'headerForCategoryContactInformation', [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::LABEL => __('Contact Info'),
        ]));
        /** Email */
        $wp_customize->add_setting(self::SITE_EMAIL, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::EMAIL,
        ]);
        $wp_customize->add_control(self::SITE_EMAIL, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::EMAIL,
            CustomizerControlArgs::LABEL => __('Email Address'),
        ]);
        /** Phone */
        $wp_customize->add_setting(self::SITE_PHONES, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
        ]);
        $wp_customize->add_control(self::SITE_PHONES, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEL,
            CustomizerControlArgs::LABEL => __('Phone Number', 'wptheme'),
        ]);
        /** Skype Username */
        $wp_customize->add_setting(self::SITE_SKYPE, [
            CustomizerControlArgs::TYPE => CustomizerSettingType::OPTION,
        ]);
        $wp_customize->add_control(self::SITE_SKYPE, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Skype Username', 'wptheme'),
        ]);
        /** Address */
        $wp_customize->add_setting(self::SITE_ADDRESS, [
            CustomizerControlArgs::TYPE => CustomizerSettingType::OPTION,
        ]);
        $wp_customize->add_control(self::SITE_ADDRESS, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Address'),
        ]);
        /** Members Panel */
        $wp_customize->add_setting('headerForCategoryMembers');
        $wp_customize->add_control(new CustomizerControlSeparator($wp_customize, 'headerForCategoryMembers', [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::LABEL => __('Membership'),
        ]));

        /** Members Restrict Access */
        /* Enable/Disable PayPal Payments */
        $wp_customize->add_setting(self::SITE_REGISTRATION, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::STD => false,
            CustomizerSettingArgs::TRANSPORT => CustomizerTransport::POST_MESSAGE,
        ]);
        $wp_customize->add_control(self::SITE_REGISTRATION, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::RADIO,
            CustomizerControlArgs::LABEL => __('Users can register', 'wptheme'),
            CustomizerControlArgs::CHOICES => [
                true => __('Yes'),
                false => __('No'),
            ],
        ]);
        $wp_customize->add_setting(self::BACKEND_ACCESS_LEVEL, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::TRANSPORT => CustomizerTransport::POST_MESSAGE,
            CustomizerSettingArgs::STD => '0',
        ]);
        $wp_customize->add_control(self::BACKEND_ACCESS_LEVEL, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::SELECT,
            CustomizerControlArgs::LABEL => __('Restrict Admin Side Access', 'wptheme'),
            CustomizerControlArgs::DESCRIPTION => __('To any user level equal or below the selected', 'wptheme'),
            CustomizerControlArgs::CHOICES => [
                '0' => __('Subscriber'),
                '1' => __('Contributor'),
                '2' => __('Author'),
                '7' => __('Editor'),
            ],
        ]);
        /* Enable/Disable Message Copy */
        $wp_customize->add_setting(self::AGENTS_EMAIL_CC_ENABLE, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::TRANSPORT => CustomizerTransport::POST_MESSAGE,
            CustomizerSettingArgs::STD => 'false',
        ]);
        $wp_customize->add_control(self::AGENTS_EMAIL_CC_ENABLE, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::RADIO,
            CustomizerControlArgs::LABEL => __('Get Copy of Message Sent to User', 'wptheme'),
            CustomizerControlArgs::CHOICES => [
                'true' => __('Yes'),
                'false' => __('No'),
            ],
        ]);
        /** Email Address to Get a Copy of Agent Message */
        $wp_customize->add_setting(self::AGENTS_EMAIL_CC, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::EMAIL,
            CustomizerSettingArgs::TRANSPORT => CustomizerTransport::POST_MESSAGE,
        ]);
        $wp_customize->add_control(self::AGENTS_EMAIL_CC, [
            CustomizerControlArgs::SECTION => CustomizerSection::IDENTITY,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::EMAIL,
            CustomizerControlArgs::LABEL => __('Email Address'),
            CustomizerControlArgs::DESCRIPTION => __("The given email address will get copy of the message", 'wptheme'),
        ]);
    }

    /**
     * Register Google Services section
     *
     * @param WP_Customize_Manager $wp_customize
     */
    function registerSectionGoogle(WP_Customize_Manager $wp_customize)
    {
        /** Google Services */
        $wp_customize->add_section(CustomizerSection::GOOGLE_SERVICES, [
            CustomizerSectionArgs::TITLE => __('Google Services', 'wptheme'),
            CustomizerSectionArgs::PRIORITY => 125,
        ]);
        /** Recaptcha */
        $wp_customize->add_setting(self::RECAPTCHA_SHOW, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::STD => 'false',
        ]);
        $wp_customize->add_control(self::RECAPTCHA_SHOW, [
            CustomizerControlArgs::SECTION => CustomizerSection::GOOGLE_SERVICES,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::RADIO,
            CustomizerControlArgs::LABEL => __('Google reCAPTCHA for Spam Protection', 'wptheme'),
            CustomizerControlArgs::DESCRIPTION => __('Enable Google reCAPTCHA on contact forms ?', 'wptheme'),
            CustomizerControlArgs::CHOICES => [
                'true' => __('Yes'),
                'false' => __('No'),
            ],
        ]);
        /** reCAPTCHA Public Key */
        $wp_customize->add_setting(self::RECAPTCHA_KEY_PUBLIC, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
            CustomizerSettingArgs::STD => '',
        ]);
        $textSignUp = __('Sign up here');
        $wp_customize->add_control(self::RECAPTCHA_KEY_PUBLIC, [
            CustomizerControlArgs::SECTION => CustomizerSection::GOOGLE_SERVICES,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Google reCAPTCHA Public Key', 'wptheme'),
            CustomizerControlArgs::DESCRIPTION => sprintf(__('Get reCAPTCHA public and private keys by %s', 'wptheme'),
                "<a href='https://www.google.com/recaptcha/admin#whyrecaptcha' target='_blank'>{$textSignUp}</a>")
        ]);
        /** reCAPTCHA Private Key */
        $wp_customize->add_setting(self::RECAPTCHA_KEY_PRIVATE, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
            CustomizerSettingArgs::STD => '',
        ]);
        $wp_customize->add_control(self::RECAPTCHA_KEY_PRIVATE, [
            CustomizerControlArgs::SECTION => CustomizerSection::GOOGLE_SERVICES,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Google reCAPTCHA Private Key', 'wptheme'),
        ]);
        /* Separator */
        $wp_customize->add_control(new CustomizerControlSeparator($wp_customize, 'map_localization_separator',
            [CustomizerControlArgs::SECTION => CustomizerSection::GOOGLE_SERVICES,]));
        /** Google Maps API Key */
        $wp_customize->add_setting(self::GOOGLE_MAP_API, [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
            CustomizerSettingArgs::STD => '',
        ]);
        $wp_customize->add_control(self::GOOGLE_MAP_API, [
            CustomizerControlArgs::SECTION => CustomizerSection::GOOGLE_SERVICES,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Google Maps API Key', 'wptheme'),
        ]);
        /** Tracking Code */
        $wp_customize->add_setting(self::GOOGLE_ANALYTICS,
            [CustomizerControlArgs::TYPE => CustomizerSettingType::OPTION]);
        $wp_customize->add_control(self::GOOGLE_ANALYTICS, [
            CustomizerControlArgs::SECTION => CustomizerSection::GOOGLE_SERVICES,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXTAREA,
            CustomizerControlArgs::LABEL => __('Google Analytics', 'wptheme'),
        ]);

    }

    /**
     * Register Payments section
     *
     * @param WP_Customize_Manager $wp_customize
     */
    function registerSectionPayments(WP_Customize_Manager $wp_customize)
    {
        $wp_customize->add_section(CustomizerSection::PAYMENTS, [
            CustomizerSectionArgs::TITLE => __('Payments', 'wptheme'),
            CustomizerSectionArgs::PRIORITY => 136,
        ]);
        /* Currency Sign  */
        $wp_customize->add_setting('theme_currency_sign', [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
            CustomizerSettingArgs::STD => '$',
        ]);
        $wp_customize->add_control('theme_currency_sign', [
            CustomizerControlArgs::SECTION => CustomizerSection::PAYMENTS,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Currency Sign', 'wptheme'),
            CustomizerControlArgs::DESCRIPTION => __('Provide currency sign. For Example: $', 'wptheme'),
        ]);

        /* Position */
        $wp_customize->add_setting('theme_currency_position', [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::STD => 'before',
        ]);
        $wp_customize->add_control('theme_currency_position', [
            CustomizerControlArgs::SECTION => CustomizerSection::PAYMENTS,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::RADIO,
            CustomizerControlArgs::LABEL => __('Position of Currency Sign', 'wptheme'),
            CustomizerControlArgs::CHOICES => [
                'before' => __('Before the numbers', 'wptheme'),
                'after' => __('After the numbers', 'wptheme'),
            ],
        ]);

        /* Number of Decimals */
        $wp_customize->add_setting('theme_decimals', [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::STD => '0',
        ]);
        $wp_customize->add_control('theme_decimals', [
            CustomizerControlArgs::SECTION => CustomizerSection::PAYMENTS,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::SELECT,
            CustomizerControlArgs::LABEL => __('Number of Decimals Points', 'wptheme'),
            CustomizerControlArgs::CHOICES => [
                '0' => 0,
                '1' => 1,
                '2' => 2,
                '3' => 3,
                '4' => 4,
                '5' => 5,
                '6' => 6,
                '7' => 7,
                '8' => 8,
                '9' => 9,
                '10' => 10,
            ],
        ]);

        /* Decimal Point Separator  */
        $wp_customize->add_setting('theme_dec_point', [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
            CustomizerSettingArgs::STD => '.',
        ]);
        $wp_customize->add_control('theme_dec_point', [
            CustomizerControlArgs::SECTION => CustomizerSection::PAYMENTS,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Decimal Point Separator', 'wptheme'),
        ]);

        /* Thousand Separator  */
        $wp_customize->add_setting('theme_thousands_sep', [
            CustomizerSettingArgs::TYPE => CustomizerSettingType::OPTION,
            CustomizerSettingArgs::SANITITZE_CALLBACK => CustomizerSanitize::TEXT,
            CustomizerSettingArgs::STD => ',',
        ]);
        $wp_customize->add_control('theme_thousands_sep', [
            CustomizerControlArgs::SECTION => CustomizerSection::PAYMENTS,
            CustomizerControlArgs::TYPE => CustomizerInputTypes::TEXT,
            CustomizerControlArgs::LABEL => __('Thousands Separator', 'wptheme'),
        ]);
    }

    function registerCustomizerDefaults(WP_Customize_Manager $wp_customize)
    {
        /** Init Defaults*/
        $this->initSelectiveRefresh($wp_customize, WPOptions::SITE_NAME);
        $this->initSelectiveRefresh($wp_customize, WPOptions::SITE_DESCRIPTION);
        $this->initSelectiveRefresh($wp_customize, self::SITE_EMAIL);
        $this->initSelectiveRefresh($wp_customize, self::SITE_PHONES);
        $this->initSelectiveRefresh($wp_customize, self::SITE_SKYPE);
        $this->initSelectiveRefresh($wp_customize, self::SITE_ADDRESS);
    }

    function initSelectiveRefresh(WP_Customize_Manager $wp_customize, $idOption)
    {
        $wp_customize->get_setting($idOption)->transport = CustomizerTransport::POST_MESSAGE;
        $wp_customize->selective_refresh->add_partial($idOption, [
            'selector' => ".$idOption",
            'render_callback' => function () use ($idOption) {
                return get_option($idOption);
            },
        ]);
    }

    static function getSiteContacts()
    {
        if (!self::$siteContacts) {
            self::$siteContacts = [
                self::SITE_ADDRESS => __("Address", 'wptheme'),
                self::SITE_EMAIL => __("Email Address"),
                self::SITE_PHONES => __("Phone Numbers", 'wptheme'),
                self::SITE_SKYPE => __("Skype Username", 'wptheme'),
            ];
        }

        return self::$siteContacts;
    }

    static function getReferencePrefixes($settings)
    {
        if (!self::$referencePrefixes) {
            //TODO For case when don't have contact page on site load with google maps
            self::$referencePrefixes = [
                //			self::SITE_ADDRESS => "https://www.google.com/maps/?q=",
                self::SITE_ADDRESS => get_home_url(null, 'contacts/'),
                self::SITE_EMAIL => "mailto:",
                self::SITE_PHONES => "tel:",
                self::SITE_SKYPE => "skype:",
            ];
        }

        return self::$referencePrefixes[$settings];
    }

    static function getSettingsIconFa($settings)
    {
        return self::$settingsIconsFa[$settings];
    }

    /** Load Styles & Scripts for: Customizer */
    function enqueueScriptsCustomizer()
    {
        $uriToDirLibs = WPUtils::getUriToLibsDir();
        wp_enqueue_style('customizer', "{$uriToDirLibs}/customizer.css");
    }

    function enqueueScriptsCustomizerPreview()
    {
        $uriToDirLibs = WPUtils::getUriToLibsDir();
        wp_enqueue_script('customizer', "{$uriToDirLibs}/customizer.js", ['jquery', 'customize-preview'],
            '', true);
    }

    function handleActionAfterSave(WP_Customize_Manager $wp_customize)
    {
        /** Section: Identity */
        $this->setDefaultsForSectionIdentity($wp_customize);
        /** Section: Google */
        $this->setDefaultsForSectionGoogle($wp_customize);
        /** Section: Payments */
        $this->setDefaultsForSectionPayments($wp_customize);
    }

    /**
     * Define defaults for section: Identity
     *
     * @param WP_Customize_Manager $wp_customize
     */
    function setDefaultsForSectionIdentity(WP_Customize_Manager $wp_customize)
    {
        $this->setDefaultsForCustomizer($wp_customize, [
            self::SITE_EMAIL,
            self::SITE_PHONES,
            self::SITE_SKYPE,
            self::SITE_ADDRESS,
            self::SITE_REGISTRATION,
            self::BACKEND_ACCESS_LEVEL,
            self::AGENTS_EMAIL_CC_ENABLE,
        ]);
    }

    /**
     * Helper function to initialize default values for settings as customizer api do not do so by default.
     *
     * @param WP_Customize_Manager $wp_customize
     * @param array $settingsIDs
     */
    function setDefaultsForCustomizer(WP_Customize_Manager $wp_customize, $settingsIDs)
    {
        if (is_array($settingsIDs) && !empty($settingsIDs)) {
            foreach ($settingsIDs as $settingID) {
                $setting = $wp_customize->get_setting($settingID);
                if ($setting) {
                    add_option($setting->id, $setting->default);
                }
            }
        }
    }

    /**
     * Set default values for Google Services section
     *
     * @param WP_Customize_Manager $wp_customize
     */
    function setDefaultsForSectionGoogle(WP_Customize_Manager $wp_customize)
    {
        $this->setDefaultsForCustomizer($wp_customize, [
            self::RECAPTCHA_SHOW,
            self::RECAPTCHA_KEY_PUBLIC,
            self::RECAPTCHA_KEY_PRIVATE,
            self::GOOGLE_MAP_API,
        ]);
    }

    /**
     * Set default values for Payments section
     *
     * @param WP_Customize_Manager $wp_customize
     */
    function setDefaultsForSectionPayments(WP_Customize_Manager $wp_customize)
    {
        $this->setDefaultsForCustomizer($wp_customize, [
            'theme_currency_sign',
            'theme_currency_position',
            'theme_decimals',
            'theme_dec_point',
            'theme_thousands_sep',
        ]);
    }
}