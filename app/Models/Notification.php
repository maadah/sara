<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'icon',
        'link',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the user that owns the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications.
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Create a notification for a user.
     */
    public static function notify($userId, $type, $title, $message, $link = null, $data = null)
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'data' => $data,
        ]);
    }

    /**
     * Create low stock notification.
     */
    public static function lowStock($userId, $product)
    {
        return self::notify(
            $userId,
            'low_stock',
            'اقتربت الكمية الخاصة بـ - ' . $product->name . ' - على النفاذ',
            'هناك حقيقة مثبتة منذ زمن طويل وهي أن المحتوى المقروء لصفحة ما سيلهي القارئ عن التركيز على',
            route('customer.products.edit', $product->id),
            ['product_id' => $product->id, 'quantity' => $product->quantity]
        );
    }

    /**
     * Create sale completed notification.
     */
    public static function saleCompleted($userId, $sale)
    {
        return self::notify(
            $userId,
            'sale_completed',
            'تم إتمام عملية بيع جديدة',
            'تم بيع منتجات بقيمة ' . number_format($sale->total) . ' ' . ($sale->currency == 'USD' ? '$' : 'د.ع') . ' - فاتورة رقم: ' . $sale->invoice_number,
            route('customer.sales.show', $sale->id),
            ['sale_id' => $sale->id, 'total' => $sale->total]
        );
    }

    /**
     * Create product added notification.
     */
    public static function productAdded($userId, $product)
    {
        return self::notify(
            $userId,
            'product_added',
            'تم إضافة منتج جديد',
            'تم إضافة المنتج "' . $product->name . '" إلى المخزون',
            route('customer.products.show', $product->id),
            ['product_id' => $product->id]
        );
    }

    /**
     * Create product updated notification.
     */
    public static function productUpdated($userId, $product)
    {
        return self::notify(
            $userId,
            'product_updated',
            'تم تحديث منتج',
            'تم تحديث بيانات المنتج "' . $product->name . '"',
            route('customer.products.show', $product->id),
            ['product_id' => $product->id]
        );
    }
}
