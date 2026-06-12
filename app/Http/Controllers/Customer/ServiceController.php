<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('customer.services.index', compact('services'));
    }

    public function create()
    {
        return view('customer.services.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:2048',
        ]);

        $service = new Service($validated);
        $service->user_id = Auth::id();
        $service->is_active = true;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $service->portfolio = [$path];
        }

        $service->save();

        return redirect()->route('customer.services.index')
            ->with('success', 'تم إضافة الخدمة بنجاح');
    }

    public function edit(Service $service)
    {
        $this->authorizeService($service);
        return view('customer.services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $this->authorizeService($service);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        $service->fill($validated);
        $service->is_active = $request->has('is_active');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('services', 'public');
            $service->portfolio = [$path];
        }

        $service->save();

        return redirect()->route('customer.services.index')
            ->with('success', 'تم تحديث الخدمة بنجاح');
    }

    public function destroy(Service $service)
    {
        $this->authorizeService($service);
        $service->delete();

        return redirect()->route('customer.services.index')
            ->with('success', 'تم حذف الخدمة بنجاح');
    }

    private function authorizeService(Service $service)
    {
        if ($service->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
