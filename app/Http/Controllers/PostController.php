<?php

namespace App\Http\Controllers;

use App\Models\Bleep;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request, $id)
    {
        // Include soft-deleted bleeps for the deleted state view
        $bleep = Bleep::withTrashed()->find($id);

        // If bleep doesn't exist at all (force deleted), redirect to home
        if (!$bleep) {
            return redirect('/')->with('info', 'This bleep is no longer available.');
        }

        // If deleted, show deleted view
        if ($bleep->trashed()) {
            return view('pages.bleeps.deleted', [
                'bleep' => $bleep,
                'deletedByAuthor' => $bleep->deleted_by_author
            ]);
        }

        return view('pages.bleeps.post', ['bleep' => $bleep]);
    }
}
