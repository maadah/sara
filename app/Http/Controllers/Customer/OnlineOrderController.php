<?php

namespace App\Http\Controllers\Customer;

use App\Events\ConversationUpdated;
use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\MetaApiService;

class OnlineOrderController extends Controller
{
    /**
     * Display list of online orders
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = OnlineOrder::where('user_id', $user->id)
            ->with(['lead', 'conversation.socialAccount', 'items']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by source
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Search by order number or customer info
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $orders = $query->paginate(20)->withQueryString();

        // Statistics
        $stats = [
            'total' => OnlineOrder::where('user_id', $user->id)->count(),
            'pending' => OnlineOrder::where('user_id', $user->id)->where('status', 'pending')->count(),
            'confirmed' => OnlineOrder::where('user_id', $user->id)->where('status', 'confirmed')->count(),
            'processing' => OnlineOrder::where('user_id', $user->id)->where('status', 'processing')->count(),
            'shipped' => OnlineOrder::where('user_id', $user->id)->where('status', 'shipped')->count(),
            'delivered' => OnlineOrder::where('user_id', $user->id)->where('status', 'delivered')->count(),
            'cancelled' => OnlineOrder::where('user_id', $user->id)->where('status', 'cancelled')->count(),
            'total_revenue' => OnlineOrder::where('user_id', $user->id)
                ->whereIn('status', ['delivered', 'confirmed', 'processing', 'shipped'])
                ->sum('total'),
            'today_orders' => OnlineOrder::where('user_id', $user->id)->whereDate('created_at', today())->count(),
            'today_revenue' => OnlineOrder::where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->whereIn('status', ['delivered', 'confirmed', 'processing', 'shipped'])
                ->sum('total'),
        ];

        return view('customer.online-orders.index', compact('orders', 'stats'));
    }

    /**
     * Show order details
     */
    public function show(OnlineOrder $onlineOrder)
    {
        $this->authorize('view', $onlineOrder);

        $onlineOrder->load([
            'lead',
            'conversation.socialAccount',
            'conversation.messages' => function ($query) {
                $query->latest()->limit(20);
            },
            'items.product.images',
        ]);

        $order = $onlineOrder;

        return view('customer.online-orders.show', compact('order'));
    }

    /**
     * Show form to edit an order
     */
    public function edit(OnlineOrder $onlineOrder)
    {
        $this->authorize('update', $onlineOrder);

        $user = Auth::user();
        $products = $user->products()->where('is_active', true)->get();
        $order = $onlineOrder;
        $order->load(['items.product', 'lead']);

        return view('customer.online-orders.edit', compact('order', 'products'));
    }

    /**
     * Show form to create order manually
     */
    public function create()
    {
        $user = Auth::user();
        $products = $user->products()->where('is_active', true)->get();
        $leads = $user->leads()->orderBy('name')->get();

        return view('customer.online-orders.create', compact('products', 'leads'));
    }

    /**
     * Store a new order manually
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_address' => 'nullable|string|max:500',
            'customer_city' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_cost' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $order = OnlineOrder::create([
                'user_id' => $user->id,
                'lead_id' => $request->lead_id,
                'conversation_id' => null,
                'order_number' => OnlineOrder::generateOrderNumber($user->id),
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'customer_city' => $request->customer_city,
                'source' => 'manual',
                'status' => 'pending',
                'shipping_cost' => $request->shipping_cost ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'notes' => $request->notes,
            ]);

            // Create order items
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                OnlineOrderItem::create([
                    'online_order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                ]);
            }

            // Calculate totals
            $order->calculateTotals();

            DB::commit();

            return redirect()->route('customer.online-orders.show', $order)
                ->with('success', 'تم إنشاء الطلب بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'حدث خطأ أثناء إنشاء الطلب: ' . $e->getMessage());
        }
    }

    /**
     * Update order information
     */
    public function update(Request $request, OnlineOrder $onlineOrder)
    {
        $this->authorize('update', $onlineOrder);

        $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_address' => 'nullable|string|max:500',
            'customer_city' => 'nullable|string|max:100',
            'shipping_cost' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $onlineOrder->update([
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_address' => $request->customer_address,
            'customer_city' => $request->customer_city,
            'shipping_cost' => $request->shipping_cost ?? 0,
            'discount_amount' => $request->discount_amount ?? 0,
            'notes' => $request->notes,
        ]);

        $onlineOrder->calculateTotals();

        return redirect()->route('customer.online-orders.show', $onlineOrder)
            ->with('success', 'تم تحديث الطلب بنجاح');
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, OnlineOrder $onlineOrder)
    {
        $this->authorize('update', $onlineOrder);

        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,returned',
        ]);

        $newStatus = $request->status;

        $result = DB::transaction(function () use ($onlineOrder, $newStatus, $request) {
            $order = OnlineOrder::whereKey($onlineOrder->id)
                ->lockForUpdate()
                ->with(['items', 'conversation.socialAccount'])
                ->firstOrFail();

            $previousStatus = $order->status;
            if ($previousStatus === $newStatus) {
                return [$order, $previousStatus];
            }

            // Update status based on action
            switch ($newStatus) {
                case 'confirmed':
                    $order->confirm();
                    break;
                case 'processing':
                    $order->process();
                    break;
                case 'shipped':
                    $order->ship($request->input('tracking_number'));
                    break;
                case 'delivered':
                    $order->deliver();
                    break;
                case 'cancelled':
                    $order->cancel($request->input('cancellation_reason'));
                    break;
                case 'returned':
                    $order->markReturned();
                    break;
                default:
                    $order->update(['status' => $newStatus]);
            }

            $this->applyStatusSideEffects($order, $previousStatus, $order->status);

            // Keep a simple status history
            $meta = $order->meta_data ?? [];
            $history = $meta['status_history'] ?? [];
            $history[] = [
                'from' => $previousStatus,
                'to' => $order->status,
                'at' => now()->toDateTimeString(),
                'by' => Auth::id(),
            ];
            $meta['status_history'] = array_slice($history, -50);
            $order->meta_data = $meta;
            $order->save();

            return [$order->fresh(['conversation.socialAccount', 'items']), $previousStatus];
        });

        /** @var OnlineOrder $onlineOrder */
        [$onlineOrder, $previousStatus] = $result;

        $messageSent = $this->sendOrderStatusMessage($onlineOrder, $previousStatus, $onlineOrder->status);

        if ($request->ajax()) {
            return response()->json([
                'success'       => true,
                'message'       => 'تم تحديث حالة الطلب',
                'message_sent'  => $messageSent,
                'status_label'  => $onlineOrder->status_label,
                'status_color'  => $onlineOrder->status_color,
            ]);
        }

        $flashMessage = 'تم تحديث حالة الطلب';
        if (!$messageSent) {
            return redirect()->route('customer.online-orders.show', $onlineOrder)
                ->with('success', $flashMessage)
                ->with('warning', 'لم يتم إرسال رسالة للعميل (لا يوجد محادثة مرتبطة بالطلب)');
        }

        return redirect()->route('customer.online-orders.show', $onlineOrder)
            ->with('success', $flashMessage . ' وتم إرسال رسالة للعميل');
    }

    /**
     * Apply inventory/bank side effects when order status changes.
     */
    protected function applyStatusSideEffects(OnlineOrder $order, string $previousStatus, string $newStatus): void
    {
        $meta = $order->meta_data ?? [];

        $inventoryReserved = (bool)($meta['inventory_reserved'] ?? false);
        $inventoryStrategy = $meta['inventory_strategy'] ?? null;

        // Reserve/decrease inventory when confirmed.
        $shouldReserveOnConfirm = $order->source === 'manual' || $inventoryStrategy === 'on_confirm';
        if ($previousStatus !== 'confirmed' && $newStatus === 'confirmed' && $shouldReserveOnConfirm && !$inventoryReserved) {
            $this->reserveInventory($order);
            $meta = $order->fresh()->meta_data ?? [];
        }

        // Restock inventory when cancelled/returned (only if we previously reserved it).
        if (in_array($newStatus, ['cancelled', 'returned'], true) && $inventoryReserved) {
            $this->restockInventory($order);
            $meta = $order->fresh()->meta_data ?? [];
        }

        // Bank effect: create a Sale record when delivered (no extra inventory change here).
        if ($previousStatus !== 'delivered' && $newStatus === 'delivered') {
            $this->createSaleForDeliveredOrder($order);
        }

        // Refund effect when returned after delivery: mark Sale refunded if exists.
        if ($newStatus === 'returned') {
            $this->markSaleRefundedForOrder($order);
        }
    }

    protected function reserveInventory(OnlineOrder $order): void
    {
        $order->loadMissing(['items', 'items.product']);

        $meta = $order->meta_data ?? [];
        $reservedItems = [];

        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product && !empty($item->product_name)) {
                $product = Product::where('user_id', $order->user_id)
                    ->where('is_active', true)
                    ->where(function ($q) use ($item) {
                        $q->where('name', $item->product_name)
                          ->orWhere('name', 'LIKE', '%' . $item->product_name . '%');
                    })
                    ->first();
            }

            if (!$product) {
                throw new \RuntimeException('لا يمكن خصم المخزون: المنتج غير موجود (' . ($item->product_name ?? '-') . ')');
            }

            if ($product->quantity < $item->quantity) {
                throw new \RuntimeException('لا يمكن تأكيد الطلب: الكمية غير متوفرة للمنتج ' . $product->name);
            }

            $product->decrement('quantity', $item->quantity);

            // Low stock notification (same threshold used in POS)
            if ($product->quantity <= 5 && $product->quantity > 0) {
                Notification::lowStock($order->user_id, $product);
            }

            $reservedItems[] = [
                'order_item_id' => $item->id,
                'product_id' => $product->id,
                'quantity' => (int)$item->quantity,
                'name' => $product->name,
            ];
        }

        $meta['inventory_reserved'] = true;
        $meta['inventory_reserved_at'] = now()->toDateTimeString();
        $meta['inventory_reserved_items'] = $reservedItems;
        $order->meta_data = $meta;
        $order->save();
    }

    protected function restockInventory(OnlineOrder $order): void
    {
        $meta = $order->meta_data ?? [];
        $reservedItems = $meta['inventory_reserved_items'] ?? [];

        foreach ($reservedItems as $reserved) {
            $productId = $reserved['product_id'] ?? null;
            $quantity = (int)($reserved['quantity'] ?? 0);
            if (!$productId || $quantity <= 0) {
                continue;
            }

            $product = Product::where('user_id', $order->user_id)->where('id', $productId)->first();
            if ($product) {
                $product->increment('quantity', $quantity);
            }
        }

        $meta['inventory_reserved'] = false;
        $meta['inventory_restocked_at'] = now()->toDateTimeString();
        $order->meta_data = $meta;
        $order->save();
    }

    protected function createSaleForDeliveredOrder(OnlineOrder $order): void
    {
        $order->loadMissing(['items', 'items.product']);

        $meta = $order->meta_data ?? [];
        if (!empty($meta['sale_id'])) {
            return;
        }

        // Map online order payment method to POS sale payment_method enum
        $paymentMethod = match ($order->payment_method) {
            'cash_on_delivery' => 'cash',
            'bank_transfer' => 'transfer',
            'wallet' => 'card',
            default => 'cash',
        };

        $subtotal = $order->items->sum('total');
        $discountAmount = (float)($order->discount ?? 0);
        $total = (float)($order->total ?? 0);

        $sale = Sale::create([
            'user_id' => $order->user_id,
            'invoice_number' => Sale::generateInvoiceNumber($order->user_id),
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'discount_percentage' => 0,
            'total' => $total,
            'currency' => $order->currency,
            'notes' => 'مبيعات من الطلب ' . $order->order_number,
            'payment_method' => $paymentMethod,
            'status' => 'completed',
        ]);

        foreach ($order->items as $item) {
            $product = $item->product;
            if (!$product && !empty($item->product_name)) {
                $product = Product::where('user_id', $order->user_id)
                    ->where('is_active', true)
                    ->where(function ($q) use ($item) {
                        $q->where('name', $item->product_name)
                          ->orWhere('name', 'LIKE', '%' . $item->product_name . '%');
                    })
                    ->first();
            }

            if (!$product) {
                // sale_items.product_id is required (FK), so we must abort if we can't resolve.
                throw new \RuntimeException('لا يمكن تسجيل المبيعات: المنتج غير موجود (' . ($item->product_name ?? '-') . ')');
            }

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'total' => $item->total,
                'currency' => $order->currency,
            ]);
        }

        $meta['sale_id'] = $sale->id;
        $meta['sale_invoice_number'] = $sale->invoice_number;
        $meta['sale_created_at'] = now()->toDateTimeString();
        $order->meta_data = $meta;
        $order->save();

        Notification::saleCompleted($order->user_id, $sale);
    }

    protected function markSaleRefundedForOrder(OnlineOrder $order): void
    {
        $meta = $order->meta_data ?? [];
        $saleId = $meta['sale_id'] ?? null;
        if (empty($saleId)) {
            return;
        }

        $sale = Sale::where('user_id', $order->user_id)->where('id', $saleId)->first();
        if (!$sale) {
            return;
        }

        $sale->status = 'refunded';
        $sale->save();

        $meta['sale_refunded_at'] = now()->toDateTimeString();
        $order->meta_data = $meta;
        $order->save();
    }

    /**
     * Send confirmation message to the customer when admin confirms an AI order
     */
    protected function sendConfirmationMessage(OnlineOrder $onlineOrder): void
    {
        $conversation = $onlineOrder->conversation;

        if (!$conversation || !$conversation->socialAccount) {
            return;
        }

        $socialAccount = $conversation->socialAccount;
        $recipientId = $conversation->participant_id;
        $accessToken = $socialAccount->provider_token;

        if (!$recipientId || !$accessToken) {
            return;
        }

        $text = "تم تأكيد طلبك رقم {$onlineOrder->order_number}. شكراً لثقتك، سنجهز الطلب ونرسل لك التحديثات هنا.";

        try {
            $this->sendOrderTextMessage($onlineOrder, $text, [
                'auto' => 'order_confirmed',
                'order_id' => $onlineOrder->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending confirmation message', [
                'order_id' => $onlineOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a customer message for every status change.
     */
    protected function sendOrderStatusMessage(OnlineOrder $order, string $previousStatus, string $newStatus): bool
    {
        if ($previousStatus === $newStatus) {
            return true;
        }

        $text = match ($newStatus) {
            'confirmed'  => "تم تأكيد طلبك رقم {$order->order_number}. شكراً لثقتك، سنجهز الطلب ونرسل لك التحديثات هنا.",
            'processing' => "طلبك رقم {$order->order_number} قيد التجهيز الآن.",
            'shipped'    => "تم شحن طلبك رقم {$order->order_number}. راح نوصله لك قريباً.",
            'delivered'  => "تم توصيل طلبك رقم {$order->order_number}. شكراً لتعاملك معنا!",
            'cancelled'  => "تم إلغاء طلبك رقم {$order->order_number}. إذا تحب تسوي طلب جديد اكتبلنا.",
            'returned'   => "تم تسجيل مرتجع الطلب رقم {$order->order_number}.",
            default      => "تم تحديث حالة طلبك رقم {$order->order_number} إلى: {$order->status_label}.",
        };

        return $this->sendOrderTextMessage($order, $text, [
            'auto'        => 'order_status_update',
            'order_id'    => $order->id,
            'from_status' => $previousStatus,
            'to_status'   => $newStatus,
        ]);
    }

    /**
     * Low-level send + persist Message record.
     */
    protected function sendOrderTextMessage(OnlineOrder $onlineOrder, string $text, array $meta = []): bool
    {
        $conversation = $onlineOrder->conversation;

        if (!$conversation) {
            // No linked conversation: skip silently for:
            // - Manual orders (admin-created, no chat channel by design)
            // - AI web-chat orders (conversation_id is null — no social-media channel to reply on)
            if ($onlineOrder->source === 'manual' || empty($onlineOrder->conversation_id)) {
                return true;
            }
            // Has a conversation_id but the record is missing/deleted — log and skip
            Log::warning('sendOrderTextMessage: order has conversation_id but record not found — no message sent', [
                'order_id'        => $onlineOrder->id,
                'order_number'    => $onlineOrder->order_number,
                'conversation_id' => $onlineOrder->conversation_id,
            ]);
            return false;
        }

        if (!$conversation->socialAccount) {
            Log::warning('sendOrderTextMessage: conversation has no socialAccount — no message sent', [
                'order_id' => $onlineOrder->id,
                'conversation_id' => $conversation->id,
            ]);
            return false;
        }

        $socialAccount = $conversation->socialAccount;
        $recipientId   = $conversation->participant_id;
        $accessToken   = $socialAccount->provider_token;

        if (!$recipientId || !$accessToken) {
            Log::warning('sendOrderTextMessage: missing recipientId or accessToken', [
                'order_id'      => $onlineOrder->id,
                'recipient_id'  => $recipientId,
                'has_token'     => !empty($accessToken),
            ]);
            return false;
        }

        $platform = ($conversation->platform === 'facebook' || $socialAccount->provider === 'facebook_page')
            ? 'facebook' : 'instagram';

        $pageId = $socialAccount->provider_id;
        if ($platform === 'instagram') {
            $pageId = data_get($socialAccount->meta_data, 'facebook_page_id', $socialAccount->provider_id);
        }

        $metaApi = app(MetaApiService::class);

        Log::info('sendOrderTextMessage: sending', [
            'order_id'     => $onlineOrder->id,
            'platform'     => $platform,
            'recipient_id' => $recipientId,
            'page_id'      => $pageId,
            'via_proxy'    => $metaApi->isProxy(),
        ]);

        $data = $metaApi->sendMessage(
            $pageId,
            $accessToken,
            $recipientId,
            $text,
            $platform
        );

        if (!$data) {
            Log::error('sendOrderTextMessage: MetaApiService::sendMessage returned null', [
                'order_id' => $onlineOrder->id,
            ]);
            return false;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $onlineOrder->user_id,
            'external_id'     => $data['message_id'] ?? null,
            'direction'       => 'outgoing',
            'content'         => $text,
            'message_type'    => 'text',
            'status'          => 'sent',
            'is_read'         => true,
            'is_from_customer' => false,
            'is_ai_generated' => false,
            'meta_data'       => $meta,
        ]);

        $conversation->updateWithNewMessage($message);
        event(new NewMessageReceived($message));
        event(new ConversationUpdated($conversation->fresh()));

        return true;
    }

    /**
     * Go to conversation from order
     */
    public function conversation(OnlineOrder $onlineOrder)
    {
        $this->authorize('view', $onlineOrder);

        if ($onlineOrder->conversation) {
            return redirect()->route('customer.inbox.show', $onlineOrder->conversation);
        }

        return redirect()->route('customer.online-orders.show', $onlineOrder)
            ->with('error', 'لا توجد محادثة مرتبطة بهذا الطلب');
    }

    /**
     * Delete an order
     */
    public function destroy(OnlineOrder $onlineOrder)
    {
        $this->authorize('delete', $onlineOrder);

        // Only allow deleting pending or cancelled orders
        if (!in_array($onlineOrder->status, ['pending', 'cancelled'])) {
            return back()->with('error', 'لا يمكن حذف طلب قيد المعالجة');
        }

        $onlineOrder->delete();

        return redirect()->route('customer.online-orders.index')
            ->with('success', 'تم حذف الطلب بنجاح');
    }

    /**
     * Print order
     */
    public function print(OnlineOrder $onlineOrder)
    {
        $this->authorize('view', $onlineOrder);

        $onlineOrder->load(['items.product', 'lead']);
        $order = $onlineOrder;

        return view('customer.online-orders.print', compact('order'));
    }

    /**
     * Export orders
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        $query = OnlineOrder::where('user_id', $user->id)->with(['items', 'lead']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->get();

        $filename = 'orders_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($file, [
                'رقم الطلب',
                'اسم العميل',
                'الهاتف',
                'المدينة',
                'العنوان',
                'المصدر',
                'الحالة',
                'المنتجات',
                'إجمالي المنتجات',
                'الشحن',
                'الخصم',
                'الإجمالي النهائي',
                'تاريخ الطلب',
            ]);

            foreach ($orders as $order) {
                $products = $order->items->map(function ($item) {
                    return $item->product_name . ' x' . $item->quantity;
                })->implode(', ');

                fputcsv($file, [
                    $order->order_number,
                    $order->customer_name,
                    $order->customer_phone,
                    $order->customer_city ?? '-',
                    $order->customer_address ?? '-',
                    $order->source_label,
                    $order->status_label,
                    $products,
                    number_format($order->subtotal, 0),
                    number_format($order->shipping_cost, 0),
                    number_format($order->discount_amount, 0),
                    number_format($order->total, 0),
                    $order->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

