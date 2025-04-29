<?php
/**
 * 网站维护功能插件
 * 
 * @package QzMaintenanceMode
 * @author 狐亦酱
 * @version 1.0.0
 * @link https://hueoo.com
 * @desc 提供网站维护模式开关、自定义维护页面、访问白名单等功能
 * @date 2025-04-29
 * @update 2025-04-29
 */

class QzMaintenanceMode_Plugin implements Typecho_Plugin_Interface
{
    /* 激活插件方法 */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('QzMaintenanceMode_Plugin', 'checkMaintenance');
    }

    /* 禁用插件方法 */
    public static function deactivate(){}

    /* 插件配置方法 */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 基本设置
        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable',
            array('1' => '启用', '0' => '禁用'),
            '0',
            _t('是否启用维护模式')
        );
        $form->addInput($enable);

        // 白名单设置
        $whitelistEnable = new Typecho_Widget_Helper_Form_Element_Radio(
            'whitelistEnable',
            array('1' => '启用', '0' => '禁用'),
            '0',
            _t('是否启用白名单功能')
        );
        $form->addInput($whitelistEnable);

        // IP白名单
        $ipWhitelist = new Typecho_Widget_Helper_Form_Element_Textarea(
            'ipWhitelist',
            NULL,
            '127.0.0.1',
            _t('IP白名单列表'),
            _t('每行一个IP地址，支持CIDR格式（如192.168.1.0/24）')
        );
        $form->addInput($ipWhitelist);

        // URL白名单
        $urlWhitelist = new Typecho_Widget_Helper_Form_Element_Textarea(
            'urlWhitelist',
            NULL,
            '',
            _t('URL白名单列表'),
            _t('每行一个URL路径（如/about或/contact）')
        );
        $form->addInput($urlWhitelist);

        // 维护时间设置
        $startTime = new Typecho_Widget_Helper_Form_Element_Text(
            'startTime',
            NULL,
            date('Y-m-d\TH:i', strtotime('+1 hour')),
            _t('维护开始时间'),
            _t('格式: YYYY-MM-DDTHH:MM (如: 2025-04-25T22:52:00)')
        );
        $form->addInput($startTime);

        $endTime = new Typecho_Widget_Helper_Form_Element_Text(
            'endTime',
            NULL,
            date('Y-m-d\TH:i', strtotime('+2 hours')),
            _t('维护结束时间'),
            _t('格式: YYYY-MM-DDTHH:MM (如: 2025-04-28T22:53:00)')
        );
        $form->addInput($endTime);

        // 维护前内容
        $preTitle = new Typecho_Widget_Helper_Form_Element_Text(
            'preTitle',
            NULL,
            '博客将于',
            _t('维护前标题')
        );
        $form->addInput($preTitle);

        $preContent = new Typecho_Widget_Helper_Form_Element_Textarea(
            'preContent',
            NULL,
            '维护即将开始，维护期间所有页面不可访问',
            _t('维护前内容')
        );
        $form->addInput($preContent);

        // 维护中内容
        $maintenanceTitle = new Typecho_Widget_Helper_Form_Element_Text(
            'maintenanceTitle',
            NULL,
            '网站正在例行维护中',
            _t('维护期间标题')
        );
        $form->addInput($maintenanceTitle);

        $maintenanceContent = new Typecho_Widget_Helper_Form_Element_Textarea(
            'maintenanceContent',
            NULL,
            '网站正在维护中，请在倒计时结束后访问！',
            _t('维护期间内容')
        );
        $form->addInput($maintenanceContent);

        // 维护后内容
        $postTitle = new Typecho_Widget_Helper_Form_Element_Text(
            'postTitle',
            NULL,
            '例行维护结束',
            _t('维护后标题')
        );
        $form->addInput($postTitle);

        $postContent = new Typecho_Widget_Helper_Form_Element_Textarea(
            'postContent',
            NULL,
            '维护结束，您可以正常访问，如果还停留在此页面，请刷新页面即可',
            _t('维护后内容')
        );
        $form->addInput($postContent);

        // 其他设置
        $websiteName = new Typecho_Widget_Helper_Form_Element_Text(
            'websiteName',
            NULL,
            '青竹小轩丨网站例行维护中...',
            _t('网站名称')
        );
        $form->addInput($websiteName);

        $icpNumber = new Typecho_Widget_Helper_Form_Element_Text(
            'icpNumber',
            NULL,
            '豫ICP备2024102831号-1',
            _t('ICP备案号')
        );
        $form->addInput($icpNumber);
    }

    /* 个人用户的配置方法 */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /* 检查IP是否在白名单中 */
    private static function isIpAllowed($ip, $whitelist)
    {
        $whitelist = array_map('trim', explode("\n", $whitelist));
        
        foreach ($whitelist as $allowed) {
            if (strpos($allowed, '/') !== false) {
                // CIDR格式检查
                list($subnet, $mask) = explode('/', $allowed);
                $subnet = ip2long($subnet);
                $ip = ip2long($ip);
                $mask = -1 << (32 - $mask);
                $subnet &= $mask;
                if (($ip & $mask) == $subnet) {
                    return true;
                }
            } elseif ($ip === $allowed) {
                // 精确IP匹配
                return true;
            }
        }
        return false;
    }

    /* 检查URL是否在白名单中 */
    private static function isUrlAllowed($requestUrl, $whitelist)
    {
        $whitelist = array_map('trim', explode("\n", $whitelist));
        
        foreach ($whitelist as $allowedUrl) {
            if (strpos($requestUrl, $allowedUrl) === 0) {
                return true;
            }
        }
        return false;
    }

    /* 获取客户端真实IP */
    private static function getClientIp()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $ip;
    }

    /* 检查维护状态 */
    public static function checkMaintenance($archive)
    {
        $options = Helper::options()->plugin('QzMaintenanceMode');

        // 检查是否为管理员，若是则直接放行
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin() && $user->pass('administrator', true)) {
            return;
        }
        
        // 检查白名单
        if ($options->whitelistEnable) {
            // 检查IP白名单
            $clientIp = self::getClientIp();
            if (self::isIpAllowed($clientIp, $options->ipWhitelist)) {
                return; // IP在白名单中，直接放行
            }
            
            // 检查URL白名单
            $requestUrl = $_SERVER['REQUEST_URI'];
            if (self::isUrlAllowed($requestUrl, $options->urlWhitelist)) {
                return; // URL在白名单中，直接放行
            }
        }

        if (!$options->enable) {
            return;
        }
        date_default_timezone_set('Asia/Shanghai'); // 设置中国时区

        $now = new DateTime();
        $startTime = new DateTime($options->startTime);
        $endTime = new DateTime($options->endTime);

        // 维护前
        if ($now < $startTime) {
            die('维护前');
        } 
        // 维护中
        elseif ($now >= $startTime && $now <= $endTime) {
            // 获取用户组件实例

                //-------------------- 插件输出主要变量内容 Start --------------------
$scripttag = '
const apiResponse = {
    "domain": "0.0.0.0",
    "websiteName": "'.$options->websiteName.'",
    "icpNumber": "'.$options->icpNumber.'",
    "startTime": "'.$options->startTime.'",
    "endTime":   "'.$options->endTime.'",
    "preMaintenanceTitle": "'.$options->preTitle.'",
    "preMaintenanceContent": "'.$options->preContent.'",
    "maintenanceTitle": "'.$options->maintenanceTitle.'",
    "maintenanceContent": "'.$options->maintenanceContent.'",
    "postMaintenanceTitle": "'.$options->postTitle.'",
    "postMaintenanceContent": "'.$options->postContent.'"
};
';

include __DIR__ .'/assets/index.php';

/*
//-------------------- 插件输出主要变量内容 End --------------------

//-------------------- 维护页面模板 Start --------------------
//读取html
$original = file_get_contents(__DIR__ ."/assets/index.html");

//替换 contdata 数据变量
$search = "contdata";
$replace = $scripttag;
$result = str_replace($search, $replace, $original);

//替换 scriptdata 数据变量
$search = "scriptdata";
$replace = file_get_contents(__DIR__ ."/assets/script.js");
$result = str_replace($search, $replace, $result);

echo $result;
*/
//-------------------- 维护页面模板 End --------------------
            exit;
        } 
        // 维护后
        elseif ($now > $endTime) { return;}
       
    }
}
?>    