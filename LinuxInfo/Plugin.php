<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 查看系统信息，仅限 Linux 系统，基于<a href="https://www.dbkuaizi.com" target="_blank">两双筷子</a>的插件进行二次开发，<a href="https://github.com/Chocola-X/Typecho-LinuxInfo" target="_blank">使用帮助</a>
 *
 * @package LinuxInfo
 * @author GTX690战术核显卡导弹
 * @version 1.1.0
 * @link https://www.nekopara.uk
 */
class LinuxInfo_Plugin implements Typecho_Plugin_Interface
{
    // 插件名称
    protected static $plugin_name = 'LinuxInfo';

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // 如果当前系统不是 linux 则抛出错误
        if(PHP_OS != 'Linux') {
            throw new Typecho_Plugin_Exception(_t("此插件仅支持 Linux 系统"));
        }
        // 在后端页面底部 注册方法
        Typecho_Plugin::factory('admin/footer.php')->begin = array('LinuxInfo_Plugin', 'render');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        /** 分类名称 */
        $show_item = array(
            'cpu_info'    =>  _t('CPU使用情况') . '（ CPU 使用率、系统负载）',
                           'mem_info'    =>  _t('内存使用情况') . '（内存使用率、SWAP交换分区）',
                           'disk_info'    =>  _t('磁盘使用情况') . '（磁盘使用率、磁盘已用空间、磁盘总空间）',
                           'sys_info'    =>  _t('服务器信息') . ' （系统运行时间、服务器软件、PHP 版本、SAPI 接口、PHP 内存限制、上传文件限制）',
        );

        // 设置默认选中
        $show_option_value = ['cpu_info','mem_info','disk_info'];
        $ShowInfo = new Typecho_Widget_Helper_Form_Element_Checkbox('show_items',$show_item, $show_option_value, _t('选择显示系统信息'));
        $form->addInput($ShowInfo->multiMode());

        $separator = new Typecho_Widget_Helper_Form_Element_Text('separator', NULL, ' ★ ', _t('分隔符'), '设置数据项分割字符');
        $form->addInput($separator);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function render()
    {
        $show_items = Typecho_Widget::widget('Widget_Options')->plugin(self::$plugin_name)->show_items;
        $separator = Typecho_Widget::widget('Widget_Options')->plugin(self::$plugin_name)->separator;

        // 获取 linux 系统消息
        $info_str = '';
        $show_items_max = count($show_items) - 1;
        foreach ($show_items as $show_item_k => $show_item) {
            $method = $show_item;
            if (method_exists(__CLASS__, $method)) {
                $info_str .= self::$method();
            }
            if ($show_items_max != $show_item_k) {
                $info_str .= $separator;
            }
        }

        echo <<<STR
        <div style="color: #999; line-height: 1.8; text-align: center;">
        $info_str
        </div>
        <div style="height:40px;display:block;"></div>
        STR;
    }

    // 获取CPU信息
    private static function cpu_info(){
        function GetCoreInformation() {
            $data = file('/proc/stat');
            $cores = [];
            foreach( $data as $line ) {
                if( preg_match('/^cpu[0-9]/', $line) ) {
                    $info = explode(' ', $line);
                    $cores[] = [
                        'user' => $info[1],
                        'nice' => $info[2],
                        'sys' => $info[3],
                        'idle' => $info[4],
                        'iowait' => $info[5],
                        'irq' => $info[6],
                        'softirq' => $info[7]
                    ];
                }
            }
            return $cores;
        }

        function GetCpuPercentages($stat1, $stat2) {
            if(count($stat1) !== count($stat2)){
                return;
            }
            $cpus = [];
            for( $i = 0, $l = count($stat1); $i < $l; $i++) {
                $dif = [];
                $dif['user'] = $stat2[$i]['user'] - $stat1[$i]['user'];
                $dif['nice'] = $stat2[$i]['nice'] - $stat1[$i]['nice'];
                $dif['sys'] = $stat2[$i]['sys'] - $stat1[$i]['sys'];
                $dif['idle'] = $stat2[$i]['idle'] - $stat1[$i]['idle'];
                $dif['iowait'] = $stat2[$i]['iowait'] - $stat1[$i]['iowait'];
                $dif['irq'] = $stat2[$i]['irq'] - $stat1[$i]['irq'];
                $dif['softirq'] = $stat2[$i]['softirq'] - $stat1[$i]['softirq'];
                $total = array_sum($dif);
                $cpu = [];
                foreach($dif as $x=>$y) $cpu[$x] = round($y / $total * 100, 2);
                $cpus['CPU' . $i] = $cpu;
            }
            return $cpus;
        }

        $stat1 = GetCoreInformation();
        sleep(1);
        $stat2 = GetCoreInformation();
        $data = GetCpuPercentages($stat1, $stat2);

        $overall_cpu_usage = 0;
        $core_details = [];

        foreach ($data as $core => $usage) {
            $core_usage = 100 - $usage['idle'];
            $overall_cpu_usage += $core_usage;
            $core_details[] = "{$core}: " . number_format($core_usage, 2) . "%";
        }

        $overall_cpu_usage = round($overall_cpu_usage / count($data), 2);
        $core_details_str = implode(", ", $core_details);

        $sys_load = sys_getloadavg();
        $sys_load_1min = number_format($sys_load[0], 3);
        $sys_load_5min = number_format($sys_load[1], 3);
        $sys_load_15min = number_format($sys_load[2], 3);

        return <<<CPU_INFO
        <span style="cursor: default" title="CPU使用：{$core_details_str}
        系统负载：1分钟：{$sys_load_1min} 5分钟：{$sys_load_5min} 15分钟：{$sys_load_15min}
        ">CPU: {$overall_cpu_usage} %</span>
        CPU_INFO;
    }

    // 获取内存信息
    private static function mem_info(){
        if (false === ($str = @file("/proc/meminfo"))) return false;

        $str = implode("", $str);

        preg_match_all("/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buf);
        preg_match_all("/Buffers\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buffers);
        $mem_info['memTotal'] = round($buf[1][0]/1024, 2);

        $mem_info['memFree'] = round($buf[2][0]/1024, 2);

        $mem_info['memBuffers'] = round($buffers[1][0]/1024, 2);
        $mem_info['memCached'] = round($buf[3][0]/1024, 2);

        $mem_info['memUsed'] = $mem_info['memTotal']-$mem_info['memFree'];

        $mem_info['memPercent'] = (floatval($mem_info['memTotal'])!=0)?round($mem_info['memUsed']/$mem_info['memTotal']*100,2):0;

        $mem_info['memRealUsed'] = $mem_info['memTotal'] - $mem_info['memFree'] - $mem_info['memCached'] - $mem_info['memBuffers']; //真实内存使用
        $mem_info['memRealFree'] = $mem_info['memTotal'] - $mem_info['memRealUsed']; //真实空闲
        $mem_info['memRealPercent'] = (floatval($mem_info['memTotal'])!=0)?round($mem_info['memRealUsed']/$mem_info['memTotal']*100,2):0; //真实内存使用率

        $mem_info['memCachedPercent'] = (floatval($mem_info['memCached'])!=0)?round($mem_info['memCached']/$mem_info['memTotal']*100,2):0; //Cached内存使用率

        $mem_info['swapTotal'] = round($buf[4][0]/1024, 2);

        $mem_info['swapFree'] = round($buf[5][0]/1024, 2);

        $mem_info['swapUsed'] = round($mem_info['swapTotal']-$mem_info['swapFree'], 2);

        $mem_info['swapPercent'] = (floatval($mem_info['swapTotal'])!=0)?round($mem_info['swapUsed']/$mem_info['swapTotal']*100,2):0;
        $mem_per = round($mem_info['memRealUsed'] / $mem_info['memTotal'] * 100, 2);
        return <<<MEM_INFO
        <span style="cursor: default" title="内存占用：{$mem_info['memRealUsed']} MB / {$mem_info['memTotal']} MB
        交换空间占用：{$mem_info['swapUsed']} MB / {$mem_info['swapTotal']} MB
        ">内存: {$mem_per} %</span>
        MEM_INFO;
    }

    // 获取磁盘信息
    private static function disk_info(){
        $dt = round(@disk_total_space(".")/(1024*1024*1024), 2); //总
        $df = round(@disk_free_space(".")/(1024*1024*1024), 2); //可用
        $du = $dt-$df; //已用
        $disk_use = round($du / $dt * 100, 2);
        return <<<DISK_INFO
        <span style="cursor: default" title="已用硬盘空间：{$du} GB / {$dt} GB">硬盘: {$disk_use} %</span>
        DISK_INFO;
    }

    // 获取 系统信息
    private static function sys_info()
    {
        if (false === ($str = @file("/proc/uptime"))) return false;

        $str = explode(" ", implode("", $str));

        $str = trim($str[0]);

        $min = $str / 60;

        $hours = $min / 60;

        $days = floor($hours / 24);

        $hours = floor($hours - ($days * 24));

        $min = floor($min - ($days * 60 * 24) - ($hours * 60));

        if ($days !== 0) $uptime = $days."天";

        if ($hours !== 0) $uptime .= $hours."小时";

        $uptime .= $min."分钟";
        $php['version'] = PHP_VERSION;
        $php['sapi'] = php_sapi_name();
        $php['mem_max'] = get_cfg_var('memory_limit');
        $php['up_max'] = get_cfg_var('upload_max_filesize');

        return <<<SYS_INFO
        服务器信息:
        <br>持续运行：{$uptime}，Web服务器：{$_SERVER['SERVER_SOFTWARE']}，PHP 版本：{$php['version']}，SAPI 接口：{$php['sapi']}，PHP内存限制：{$php['mem_max']}，上传文件限制：{$php['up_max']}
        SYS_INFO;

    }
}
