<?php

namespace app\controller;

use app\BaseController;
use app\service\OrderService;
use think\facade\Log;
use think\Request;

class OrderController extends BaseController
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
        
    }

    //  public function __construct(App $app)
    // {
    //     // 从容器中获取服务
    //     $this->orderService = $app->make(OrderService::class);
    //     var_dump("OrderController __construct");
    // }

    /**
     * 创建订单
     * POST /order/create
     */
    public function create(Request $request)
    {
        $userId = $request->param('user_id/d', 0);
        $addressId = $request->param('address_id/d', 0);
        $items = $request->param('items/a', []);
        $remark = $request->param('remark/s', '');

        if ($userId <= 0) {
            return $this->error('用户ID无效');
        }

        if ($addressId <= 0) {
            return $this->error('收货地址ID无效');
        }

        if (empty($items) || !is_array($items)) {
            return $this->error('商品列表不能为空');
        }

        try {
            $data = [
                'address_id' => $addressId,
                'items' => $items,
                'remark' => $remark,
            ];
           
            $result = $this->orderService->createOrder($userId, $data);

            return $this->success('订单创建成功', $result);

        } catch (\Exception $e) {
            Log::error('创建订单失败: ' . $e->getMessage());
            return $this->error($e->getMessage() ?: '订单创建失败，请稍后重试');
        }
    }

    /**
     * 取消订单
     * POST /order/cancel
     */
    public function cancel(Request $request)
    {
        $orderNo = $request->param('order_no/s', '');
        $userId = $request->param('user_id/d', 0);
        $reason = $request->param('reason/s', '用户主动取消');
        $mode = $request->param('mode/s', 'dual'); // sync / async / dual

        if (empty($orderNo)) {
            return $this->error('订单号不能为空');
        }

        try {
            $result = $this->orderService->cancelOrder($orderNo, $userId, $reason, $mode);

            return $this->success('订单取消成功', $result);

        } catch (\Exception $e) {
            Log::error('取消订单失败: ' . $e->getMessage());
            return $this->error($e->getMessage() ?: '订单取消失败，请稍后重试');
        }
    }

    /**
     * 获取订单列表
     * GET /order/list
     */
    public function list(Request $request)
    {
        $userId = $request->param('user_id/d', 0);
        $page = $request->param('page/d', 1);
        $size = $request->param('size/d', 10);

        if ($userId <= 0) {
            return $this->error('用户ID无效');
        }

        try {
            $result = $this->orderService->getOrderList($userId, $page, $size);

            return $this->success('获取成功', $result);

        } catch (\Exception $e) {
            Log::error('获取订单列表失败: ' . $e->getMessage());
            return $this->error('获取订单列表失败，请稍后重试');
        }
    }

    /**
     * 获取订单详情
     * GET /order/detail
     */
    public function detail(Request $request)
    {
        $orderNo = $request->param('order_no/s', '');

        if (empty($orderNo)) {
            return $this->error('订单号不能为空');
        }

        try {
            $result = $this->orderService->getOrderDetail($orderNo);

            return $this->success('获取成功', $result);

        } catch (\Exception $e) {
            Log::error('获取订单详情失败: ' . $e->getMessage());
            return $this->error('获取订单详情失败，请稍后重试');
        }
    }

    public function getOrderDetail(Request $request)
    {
        $orderId = $request->param('orderId/d', 0);
        if (empty($orderId)) {
            return $this->error('订单号不能为空');
        }

        try {
            $result = $this->orderService->getOrderDetail($orderId);
            return $this->success('获取成功', $result);
        } catch (\Exception $e) {
            Log::error('获取订单详情失败: ' . $e->getMessage());
            return $this->error('获取订单详情失败，请稍后重试');
        }
    }

    // 辅助方法：统一成功响应
    protected function success($msg = '操作成功', $data = [])
    {
        return json([
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
        ]);
    }

    // 辅助方法：统一错误响应
    protected function error($msg = '操作失败', $code = 1)
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => [],
        ]);
    }
}