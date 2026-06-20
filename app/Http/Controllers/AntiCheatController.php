<?php

namespace App\Http\Controllers;

use App\Services\AntiCheatService;
use Illuminate\Http\Request;

class AntiCheatController extends Controller
{
     public function __construct(protected AntiCheatService $antiCheat) {}

    public function checkFrame(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'image'      => 'required|image',
        ]);

        $result = $this->antiCheat->analyzeFrame(
            $request->input('session_id'),
            $request->file('image')
        );

        // مثال: لو الحالة TERMINATED، سجلها في الداتابيز
        if ($result['anti_cheat']['status'] === 'TERMINATED') {
            // مثال: تحديث جلسة الامتحان في DB
            // ExamSession::where('session_id', $result['session_id'])->update(['status' => 'terminated']);
        }

        return response()->json($result);
    }
    //
}
