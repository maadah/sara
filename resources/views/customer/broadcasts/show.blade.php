@extends('layouts.customer')

@section('title', 'تفاصيل حملة البث')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-800">{{ $broadcast->name }}</h2>
            <p class="text-gray-500 mt-1">تفاصيل حملة البث</p>
        </div>
        <a href="{{ route('customer.broadcasts.index') }}" class="text-[#00A8E8] hover:text-[#00658B] font-medium flex items-center gap-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            العودة
        </a>
    </div>

    {{-- Status Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-xs text-gray-500 mb-1">الحالة</p>
                @php
                    $statusColors = [
                        'draft' => 'bg-gray-100 text-gray-700',
                        'scheduled' => 'bg-blue-100 text-blue-700',
                        'sending' => 'bg-yellow-100 text-yellow-700',
                        'completed' => 'bg-green-100 text-green-700',
                        'failed' => 'bg-red-100 text-red-700',
                    ];
                    $statusLabels = [
                        'draft' => 'مسودة',
                        'scheduled' => 'مجدول',
                        'sending' => 'جاري الإرسال',
                        'completed' => 'مكتمل',
                        'failed' => 'فشل',
                    ];
                @endphp
                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statusColors[$broadcast->status] ?? 'bg-gray-100 text-gray-700' }}">
                    {{ $statusLabels[$broadcast->status] ?? $broadcast->status }}
                </span>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">المستلمين</p>
                <p class="text-lg font-bold text-gray-800">{{ $broadcast->total_recipients }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">تم الإرسال</p>
                <p class="text-lg font-bold text-green-600">{{ $broadcast->sent_count }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">فشل</p>
                <p class="text-lg font-bold text-red-600">{{ $broadcast->failed_count }}</p>
            </div>
        </div>
    </div>

    {{-- Message Content --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">نص الرسالة</h3>
        <div class="bg-gray-50 rounded-xl p-4 text-gray-700 whitespace-pre-wrap">{{ $broadcast->message }}</div>
    </div>

    {{-- Details --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">التفاصيل</h3>
        <dl class="space-y-3">
            <div class="flex justify-between">
                <dt class="text-gray-500">تاريخ الإنشاء</dt>
                <dd class="text-gray-800 font-medium">{{ $broadcast->created_at->format('Y-m-d H:i') }}</dd>
            </div>
            @if($broadcast->scheduled_at)
                <div class="flex justify-between">
                    <dt class="text-gray-500">موعد الإرسال</dt>
                    <dd class="text-gray-800 font-medium">{{ $broadcast->scheduled_at->format('Y-m-d H:i') }}</dd>
                </div>
            @endif
            @if($broadcast->completed_at)
                <div class="flex justify-between">
                    <dt class="text-gray-500">تاريخ الاكتمال</dt>
                    <dd class="text-gray-800 font-medium">{{ $broadcast->completed_at->format('Y-m-d H:i') }}</dd>
                </div>
            @endif
            @if($broadcast->target_audience)
                <div class="flex justify-between">
                    <dt class="text-gray-500">الجمهور المستهدف</dt>
                    <dd class="text-gray-800 font-medium">
                        @php
                            $audienceLabels = [
                                'all_customers' => 'جميع العملاء',
                                'active_leads' => 'العملاء النشطين',
                                'new_leads' => 'العملاء الجدد',
                                'hot_leads' => 'العملاء المهمين',
                            ];
                        @endphp
                        @foreach($broadcast->target_audience as $audience)
                            <span class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full mr-1">{{ $audienceLabels[$audience] ?? $audience }}</span>
                        @endforeach
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    @if($broadcast->status === 'draft')
        <div class="flex justify-end">
            <form action="{{ route('customer.broadcasts.destroy', $broadcast) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه الحملة؟')">
                @csrf
                @method('DELETE')
                <button class="bg-red-500 text-white px-6 py-3 rounded-xl font-bold hover:bg-red-600 transition-all">حذف الحملة</button>
            </form>
        </div>
    @endif
</div>
@endsection
