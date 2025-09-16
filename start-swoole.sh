#!/bin/bash

# 颜色配置
echo_blue() {
    echo -e "\033[34m$1\033[0m"
}
echo_green() {
    echo -e "\033[32m$1\033[0m"
}
echo_red() {
    echo -e "\033[31m$1\033[0m"
}
echo_yellow() {
    echo -e "\033[33m$1\033[0m"
}
echo_purple() {
    echo -e "\033[35m$1\033[0m"
}

# 全局变量
LOG_FILE="/tmp/swoole.log" # 默认使用/tmp目录，因为它有正确的权限
SWOOLE_COMMAND="php think swoole"
DETAILED_LOG=false

# 帮助信息
show_help() {
    echo_blue "Swoole服务管理脚本"
    echo_blue "Usage: $0 {start|stop|restart|status|logs|version} [-v]"
    echo_blue "Options:"
    echo_blue "  -v, --verbose      显示详细日志信息"
    echo_blue "Commands:"
    echo_blue "  start              启动Swoole服务"
    echo_blue "  stop               停止Swoole服务"
    echo_blue "  restart            重启Swoole服务"
    echo_blue "  status             查看Swoole服务状态"
    echo_blue "  logs               查看Swoole服务日志"
    echo_blue "  version            查看think-swoole版本信息"
    echo_blue "  -h, --help         显示帮助信息"
    exit 0
}

# 检查Docker环境
check_docker_environment() {
    echo_green "🔍 检查Docker环境..."
    
    # 检查docker-compose是否安装
    if ! command -v docker-compose &> /dev/null; then
        echo_red "❌ docker-compose 未安装，请先安装docker-compose"
        exit 1
    else
        echo_green "✅ docker-compose 已安装在 $(command -v docker-compose)"
    fi
    
    # 检查容器状态（修复版）
    echo_green "🔍 检查容器状态..."
    # 使用更可靠的方式检查容器是否运行
    if docker-compose ps | grep -q "php.*Up"; then
        echo_green "✅ 环境检查通过"
        return 0
    else
        echo_red "❌ PHP容器未启动，请先启动容器"
        exit 1
    fi
}

# 准备日志文件
prepare_log_file() {
    echo_green "📝 准备日志文件..."
    # 确保/tmp目录有正确的权限
    docker-compose exec php sh -c "mkdir -p $(dirname $LOG_FILE) && touch $LOG_FILE && chmod 666 $LOG_FILE"
    echo_green "✅ 日志文件已准备: $LOG_FILE"
}

# 显示think-swoole版本信息
show_version() {
    check_docker_environment
    echo_green "📋 think-swoole版本信息:" 
    docker-compose exec php ${SWOOLE_COMMAND} -V
    echo_green "📋 Swoole扩展版本信息:"
    docker-compose exec php php -m | grep swoole
    docker-compose exec php php --ri swoole | head -n 2
    exit 0
}

# 启动Swoole服务
start_swoole() {
    check_docker_environment
    prepare_log_file
    
    # 检查Swoole服务是否已经在运行
    if docker-compose exec php ps aux | grep -q "swoole: manager process"; then
        echo_yellow "⚠️ swoole 已在容器中运行，无需重复启动"
        return 0
    fi
    
    echo_green "🚀 启动Swoole服务..."
    
    # 使用简单可靠的方式在后台启动Swoole服务
    DOCKER_COMMAND="cd /var/www/html && ${SWOOLE_COMMAND} -vvv >> ${LOG_FILE} 2>&1"
    
    if [ "$DETAILED_LOG" = true ]; then
        echo_green "📋 启动命令: docker-compose exec -d php sh -c '$DOCKER_COMMAND'"
    fi
    
    # 在后台启动服务
    docker-compose exec -d php sh -c "$DOCKER_COMMAND"
    
    # 验证启动是否成功
    sleep 3
    if docker-compose exec php ps aux | grep -q "swoole: manager process"; then
        echo_green "✅ Swoole服务启动成功"
        echo_green "📊 日志文件: $LOG_FILE"
        if [ "$DETAILED_LOG" = true ]; then
            echo_green "📋 最新日志:"
            docker-compose exec php tail -n 10 ${LOG_FILE} 2>/dev/null || echo_yellow "⚠️ 无法读取日志文件内容"
        fi
    else
        echo_red "❌ Swoole服务启动失败，请查看日志获取详细信息"
        docker-compose exec php cat ${LOG_FILE} 2>/dev/null || echo_red "日志文件不可用"
        # 添加更多调试信息
        echo_red "💡 调试信息:"
        docker-compose exec php ps aux | head -n 20
        return 1
    fi
}

# 停止Swoole服务
stop_swoole() {
    check_docker_environment
    
    # 检查Swoole服务是否在运行
    if ! docker-compose exec php ps aux | grep -q "swoole: manager process"; then
        echo_yellow "⚠️ Swoole服务未运行，无需停止"
        return 0
    fi
    
    echo_green "🛑 停止Swoole服务..."
    
    # 优雅停止Swoole服务
    docker-compose exec php pkill -f "swoole: manager process"
    
    # 等待进程退出
    MAX_WAIT=10
    COUNT=0
    while docker-compose exec php ps aux | grep -q "swoole: manager process" && [ $COUNT -lt $MAX_WAIT ]; do
        sleep 1
        COUNT=$((COUNT+1))
    done
    
    # 强制停止剩余的Swoole进程（如果有）
    if docker-compose exec php ps aux | grep -q "swoole"; then
        echo_yellow "⚠️ 强制停止剩余的Swoole进程..."
        docker-compose exec php pkill -f "swoole" || true
    fi
    
    echo_green "✅ Swoole服务已停止"
}

# 重启Swoole服务
restart_swoole() {
    stop_swoole
    # 确保服务完全停止
    sleep 2
    start_swoole
}

# 查看Swoole服务状态
check_status() {
    check_docker_environment
    
    # 检查Swoole服务是否在运行
    if docker-compose exec php ps aux | grep -q "swoole: manager process"; then
        echo_green "✅ swoole 在容器中运行中"
        echo_green "📋 容器内进程信息:"
        docker-compose exec php ps aux | grep -i swoole
        echo_green "🔌 容器内监听端口:"
        docker-compose exec php netstat -tuln | grep -E '9000|9501'
        echo_green "🔌 宿主机映射端口:"
        docker-compose port php 9501 2>/dev/null || echo_yellow "⚠️ 端口9501未映射"
    else
        echo_yellow "⚠️ Swoole服务未运行"
        return 1
    fi
}

# 查看Swoole服务日志
view_logs() {
    check_docker_environment
    
    echo_green "📋 查看容器内swoole日志 (最后50行)..."
    if docker-compose exec php test -f ${LOG_FILE}; then
        echo_green "📋 日志文件位置: ${LOG_FILE}"
        docker-compose exec php tail -n 50 ${LOG_FILE}
    else
        echo_yellow "⚠️ 容器内日志文件不存在: ${LOG_FILE}"
        echo_yellow "💡 尝试查看替代日志位置..."
        docker-compose exec php find /tmp -name "swoole*.log" 2>/dev/null || echo_yellow "没有找到swoole相关日志文件"
        echo_yellow "💡 查看Docker容器日志:"
        docker-compose logs php | grep -i swoole | tail -n 20 || echo_yellow "Docker日志中没有找到swoole相关信息"
    fi
}

# 解析命令行参数
parse_args() {
    COMMAND=""
    while [[ $# -gt 0 ]]; do
        case $1 in
            start|stop|restart|status|logs|version) COMMAND=$1; shift ;;
            -v|--verbose) DETAILED_LOG=true; shift ;;
            -h|--help) show_help ;;
            *) echo_red "❌ 无效参数: $1"; show_help ;;
        esac
    done
    
    # 如果没有指定命令，显示帮助
    if [ -z "$COMMAND" ]; then
        show_help
    fi
}

# 主函数
main() {
    parse_args "$@"
    
    case $COMMAND in
        start) start_swoole ;;
        stop) stop_swoole ;;
        restart) restart_swoole ;;
        status) check_status ;;
        logs) view_logs ;;
        version) show_version ;;
        *) show_help ;;
    esac
}

# 执行主函数
main "$@"