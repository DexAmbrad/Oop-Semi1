<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Models\Course;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaterialController extends Controller
{
    use Concerns\ResolvesCourseAccess;

    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $this->courseOrFail($request, $course);

        $query = $course->materials()
            ->with('uploader')
            ->when($request->filled('topic'), fn ($q) => $q->where('topic', $request->string('topic')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->latest();

        return MaterialResource::collection($query->get());
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['sometimes', 'in:link,file,note'],
            'url' => ['nullable', 'url', 'max:500', 'required_if:type,link'],
            'file' => ['nullable', 'file', 'max:20480', 'required_if:type,file'],
            'topic' => ['nullable', 'string', 'max:80'],
        ]);

        $material = $course->materials()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'link',
            'url' => $data['url'] ?? null,
            'topic' => $data['topic'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        if ($request->hasFile('file')) {
            $material->file_path = $request->file('file')->store('materials/'.$course->id, 'public');
            $material->file_name = $request->file('file')->getClientOriginalName();
            $material->type = 'file';
            $material->save();
        }

        return response()->json([
            'message' => 'Resource added.',
            'material' => new MaterialResource($material->load('uploader')),
        ], 201);
    }

    public function update(Request $request, Course $course, Material $material): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($material->course_id === $course->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $material->update($data);

        return response()->json([
            'message' => 'Resource updated.',
            'material' => new MaterialResource($material->fresh('uploader')),
        ]);
    }

    public function destroy(Request $request, Course $course, Material $material): JsonResponse
    {
        $this->courseOrFail($request, $course, 'teach');
        abort_unless($material->course_id === $course->id, 404);

        $material->delete();

        return response()->json(['message' => 'Resource removed.']);
    }
}
