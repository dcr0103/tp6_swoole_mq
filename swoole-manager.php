#!/usr/bin/env php
<?php
/**
 * Swoole 服务管理脚本
 * 
 * 用法：
 * php swoole-manager.php start   - 启动服务
 * php swoole-manager.php stop    - 停止服务
 * php swoole-manager.php restart - 重启服务
 * php swoole-manager.php status  - 查看状态
 * php swoole-manager.php logs    - 查看日志
 */

// 颜色输出函数
function colorize($text, $color) {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'purple' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// 显示帮助信息
function showHelp() {
    echo colorize("=== Swoole 服务管理脚本 ===\n", 'cyan');
    echo colorize("用法：", 'yellow') . " php swoole-manager.php [命令]\n\n";
    echo colorize("命令：\n", 'yellow');
    echo "  start    - 启动 Swoole 服务\n";
    echo "  stop     - 停止 Swoole 服务\n";
    echo "  restart  - 重启 Swoole 服务\n";
    echo "  status   - 查看服务状态\n";
    echo "  logs     - 查看实时日志\n";
    echo "  help     - 显示此帮助信息\n\n";
    echo colorize("示例：\n", 'yellow');
    echo "  php swoole-manager.php start\n";
    echo "  php swoole-manager.php restart\n";
}

// 检查服务是否在运行
function isRunning() {
    $output = shell_exec('ps aux | grep "php think swoole" | grep -v grep');
    return !empty($output);
}

// 获取进程PID
function getPid() {
    $output = shell_exec('ps aux | grep "php think swoole" | grep -v grep | awk "{print \$2}"');
    return trim($output);
}

// 启动服务
function startService() {
    if (isRunning()) {
        $pid = getPid();
        echo colorize("✅ Swoole 服务已在运行，PID: $pid\n", 'green');
        return;
    }

    echo colorize("🚀 正在启动 Swoole 服务...\n", 'cyan');
    
    // 使用 nohup 在后台启动
    $command = 'cd /var/www/html && nohup php think swoole > /var/log/swoole.log 2>&1 & echo $!';
    $pid = trim(shell_exec($command));
    
    // 等待服务启动
    sleep(3);
    
    if (isRunning()) {
        echo colorize("✅ Swoole 服务启动成功，PID: $pid\n", 'green');
        echo colorize("📊 日志文件: /var/log/swoole.log\n", 'blue');
    } else {
        echo colorize("❌ Swoole 服务启动失败，请检查日志\n", 'red');
    }
}

// 停止服务
function stopService() {
    if (!isRunning()) {
        echo colorize("⚠️ Swoole 服务未在运行\n", 'yellow');
        return;
    }

    $pid = getPid();
    echo colorize("🛑 正在停止 Swoole 服务，PID: $pid...\n", 'cyan');
    
    // 优雅停止
    shell_exec("kill $pid");
    
    // 等待服务停止
    $maxWait = 10;
    $waited = 0;
    
    while (isRunning() && $waited < $maxWait) {
        sleep(1);
        $waited++;
    }
    
    if (isRunning()) {
        // 强制停止
        shell_exec("kill -9 $pid");
        echo colorize("⚠️ 已强制停止 Swoole 服务\n", 'yellow');
    } else {
        echo colorize("✅ Swoole 服务已停止\n", 'green');
    }
}

// 重启服务
function restartService() {
    echo colorize("🔄 正在重启 Swoole 服务...\n", 'cyan');
    
    if (isRunning()) {
        stopService();
        sleep(2);
    }
    
    startService();
}

// 查看状态
function showStatus() {
    if (isRunning()) {
        $pid = getPid();
        echo colorize("✅ Swoole 服务运行中，PID: $pid\n", 'green');
        
        // 显示端口占用情况
        $ports = shell_exec('netstat -tlnp 2>/dev/null | grep php | awk "{print \$4}" | cut -d: -f2');
        if (!empty($ports)) {
            echo colorize("📡 监听端口: " . trim($ports) . "\n", 'blue');
        }
        
        // 显示内存使用情况
        $memory = shell_exec("ps -p $pid -o rss=");
        if (!empty($memory)) {
            $memoryMB = round(intval(trim($memory)) / 1024, 2);
            echo colorize("💾 内存使用: {$memoryMB} MB\n", 'blue');
        }
    } else {
        echo colorize("❌ Swoole 服务未运行\n", 'red');
    }
}

// 查看日志
function showLogs() {
    echo colorize("📋 正在查看实时日志 (按 Ctrl+C 退出)...\n", 'cyan');
    echo colorize("=" . str_repeat("=", 50) . "\n", 'blue');
    
    $logFile = '/var/log/swoole.log';
    if (!file_exists($logFile)) {
        echo colorize("⚠️ 日志文件不存在: $logFile\n", 'yellow');
        return;
    }
    
    // 显示最后50行日志
    $logs = shell_exec("tail -n 50 $logFile");
    echo $logs;
    
    // 实时跟踪
    system("tail -f $logFile");
}

// 主程序
function main($argv) {
    if (count($argv) < 2) {
        showHelp();
        return;
    }

    $command = strtolower($argv[1]);

    switch ($command) {
        case 'start':
            startService();
            break;
        case 'stop':
            stopService();
            break;
        case 'restart':
            restartService();
            break;
        case 'status':
            showStatus();
            break;
        case 'logs':
            showLogs();
            break;
        case 'help':
        case '--help':
        case '-h':
            showHelp();
            break;
        default:
            echo colorize("❌ 未知命令: $command\n", 'red');
            echo colorize("使用 'php swoole-manager.php help' 查看帮助\n", 'yellow');
    }
}

// 执行主程序
main($argv);

// 显示版权信息
if (isset($argv[1]) && !in_array($argv[1], ['logs', 'help', '--help', '-h'])) {
    echo colorize("\n📝 提示: 使用 'php swoole-manager.php help' 查看更多命令\n", 'purple');
}