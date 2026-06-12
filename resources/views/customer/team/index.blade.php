@extends('layouts.customer')
@section('title', 'إدارة الفريق')
@section('page-title', 'إدارة الفريق')

@section('content')
<div class="space-y-6">

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#00A8E8]/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $members->count() }}</p>
                    <p class="text-sm text-gray-500">أعضاء الفريق</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $members->where('team_role', 'manager')->count() }}</p>
                    <p class="text-sm text-gray-500">مديرين</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800">{{ $members->where('team_role', 'agent')->count() }}</p>
                    <p class="text-sm text-gray-500">موظفين</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Member Form --}}
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-4">إضافة عضو جديد</h3>
        <form action="{{ route('customer.team.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">الاسم *</label>
                <input type="text" name="name" required value="{{ old('name') }}" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition" placeholder="اسم العضو">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">البريد الإلكتروني *</label>
                <input type="email" name="email" required value="{{ old('email') }}" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition" placeholder="email@example.com" dir="ltr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">كلمة المرور *</label>
                <input type="password" name="password" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition" placeholder="********">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-1">الدور *</label>
                <div class="flex items-center gap-2">
                    <select name="team_role" required class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] transition">
                        <option value="agent">موظف</option>
                        <option value="manager">مدير</option>
                    </select>
                    <button type="submit" class="px-5 py-2.5 bg-gradient-to-l from-[#00A8E8] to-[#007EA7] text-white rounded-xl font-medium hover:shadow-lg transition whitespace-nowrap">
                        إضافة
                    </button>
                </div>
            </div>
        </form>
        @if($errors->any())
            <div class="mt-3 p-3 bg-red-50 text-red-700 rounded-xl text-sm">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Team Members Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">العضو</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">الدور</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">تاريخ الإضافة</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($members as $member)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#00A8E8] to-[#007EA7] flex items-center justify-center text-white font-bold">
                                    {{ mb_substr($member->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800">{{ $member->name }}</p>
                                    <p class="text-xs text-gray-400" dir="ltr">{{ $member->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <form action="{{ route('customer.team.update', $member) }}" method="POST" class="inline">
                                @csrf
                                @method('PUT')
                                <select name="team_role" onchange="this.form.submit()" class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-[#00A8E8]/20 focus:border-[#00A8E8] cursor-pointer">
                                    <option value="agent" {{ $member->team_role == 'agent' ? 'selected' : '' }}>موظف</option>
                                    <option value="manager" {{ $member->team_role == 'manager' ? 'selected' : '' }}>مدير</option>
                                </select>
                            </form>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-500">{{ $member->created_at->format('Y/m/d') }}</td>
                        <td class="px-5 py-4 text-center">
                            <form action="{{ route('customer.team.destroy', $member) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا العضو؟')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button class="px-3 py-1.5 text-xs text-red-500 hover:bg-red-50 rounded-lg transition">حذف</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-gray-400">
                            <div class="w-16 h-16 rounded-2xl bg-[#00A8E8]/10 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-8 h-8 text-[#00A8E8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <h3 class="font-bold text-gray-800 mb-1">لا يوجد أعضاء في الفريق</h3>
                            <p class="text-sm">أضف أعضاء فريقك لإدارة العمل بشكل أفضل</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection