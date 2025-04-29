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
        public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('QzMaintenanceMode_Plugin', 'checkMaintenance');
    }

        public static function deactivate(){}

        public static function config(Typecho_Widget_Helper_Form $form)
    {
        
        $enable = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable',
            array('1' => '启用', '0' => '禁用'),
            '0',
            _t('是否启用维护模式')
        );
        $form->addInput($enable);

        
        $whitelistEnable = new Typecho_Widget_Helper_Form_Element_Radio(
            'whitelistEnable',
            array('1' => '启用', '0' => '禁用'),
            '0',
            _t('是否启用白名单功能')
        );
        $form->addInput($whitelistEnable);

        
        $ipWhitelist = new Typecho_Widget_Helper_Form_Element_Textarea(
            'ipWhitelist',
            NULL,
            '127.0.0.1',
            _t('IP白名单列表'),
            _t('每行一个IP地址，支持CIDR格式（如192.168.1.0/24）')
        );
        $form->addInput($ipWhitelist);

        
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

        
        $websiteName = new Typecho_Widget_Helper_Form_Element_Text(
            'websiteName',
            NULL,
            '网站例行维护中...',
            _t('网站名称')
        );
        $form->addInput($websiteName);

        $icpNumber = new Typecho_Widget_Helper_Form_Element_Text(
            'icpNumber',
            NULL,
            '京ICP备2099000000号-1',
            _t('ICP备案号')
        );
        $form->addInput($icpNumber);
    }

        public static function personalConfig(Typecho_Widget_Helper_Form $form){}

        private static function isIpAllowed($ip, $whitelist)
    {
        $whitelist = array_map('trim', explode("\n", $whitelist));
        
        foreach ($whitelist as $allowed) {
            if (strpos($allowed, '/') !== false) {
                
                list($subnet, $mask) = explode('/', $allowed);
                $subnet = ip2long($subnet);
                $ip = ip2long($ip);
                $mask = -1 << (32 - $mask);
                $subnet &= $mask;
                if (($ip & $mask) == $subnet) {
                    return true;
                }
            } elseif ($ip === $allowed) {
                
                return true;
            }
        }
        return false;
    }

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

        public static function checkMaintenance($archive)
    {
        $options = Helper::options()->plugin('QzMaintenanceMode');

        
        $user = Typecho_Widget::widget('Widget_User');
        if ($user->hasLogin() && $user->pass('administrator', true)) {
            return;
        }
        
        
        if ($options->whitelistEnable) {
            
            $clientIp = self::getClientIp();
            if (self::isIpAllowed($clientIp, $options->ipWhitelist)) {
                return; 
            }
            
            
            $requestUrl = $_SERVER['REQUEST_URI'];
            if (self::isUrlAllowed($requestUrl, $options->urlWhitelist)) {
                return; 
            }
        }

        if (!$options->enable) {
            return;
        }
        date_default_timezone_set('Asia/Shanghai'); 

        $now = new DateTime();
        $startTime = new DateTime($options->startTime);
        $endTime = new DateTime($options->endTime);

        
        if ($now < $startTime) {
            die('维护前');
        } 
        
        elseif ($now >= $startTime && $now <= $endTime) {
            

                
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


            exit;
        } 
        
        elseif ($now > $endTime) { return;}
       
    }
}
?>    
