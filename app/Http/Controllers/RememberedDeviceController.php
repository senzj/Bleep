<?php

namespace App\Http\Controllers;

use App\Models\RememberedDevice;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class RememberedDeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(RememberedDevice $rememberedDevice)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RememberedDevice $rememberedDevice)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RememberedDevice $rememberedDevice)
    {
        //
    }

    /**
     * Remove the specified resource (revoke a remembered device).
     */
    public function destroy(Request $request, RememberedDevice $rememberedDevice)
    {
        // ensure user owns the device or is admin
        if ($rememberedDevice->user_id !== Auth::id() && ! Auth::user()->hasAdminAccess()) {
            abort(403);
        }

        $action = env('REMEMBERED_LOGOUT', 'delete');

        if ($action === 'delete') {
            $rememberedDevice->delete();
        } else { // rotate: clear user_id and rotate token & mark revoked
            $plain = Str::random(64);
            $rememberedDevice->update([
                'user_id' => null,
                'token' => hash('sha256', $plain),
                'last_used_at' => null,
                'revoked_at' => now(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
