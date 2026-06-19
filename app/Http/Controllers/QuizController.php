<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Quiz::latest()->get(),
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => Quiz::findOrFail($id),
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
        ]);

        $quiz = Quiz::create([
            'title' => $request->input('title'),
            'category' => $request->input('category'),
            'pin' => $this->makePin(),
        ]);

        return response()->json([
            'message' => 'Kuis berhasil dibuat.',
            'data' => $quiz,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $quiz = Quiz::findOrFail($id);

        $this->validate($request, [
            'title' => 'required|string|max:150',
            'category' => 'nullable|string|max:100',
        ]);

        $quiz->update($request->only(['title', 'category']));

        return response()->json([
            'message' => 'Kuis berhasil diperbarui.',
            'data' => $quiz,
        ]);
    }

    public function destroy($id)
    {
        Quiz::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Kuis berhasil dihapus.',
        ]);
    }

    private function makePin()
    {
        do {
            $pin = (string) random_int(100000, 999999);
        } while (Quiz::where('pin', $pin)->exists());

        return $pin;
    }
}
