<?php

namespace App\Http\Controllers\Api\Ta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ta\IndexDirectoriesRequest;
use App\Http\Resources\Ta\DirectoryResource;
use App\Models\Domain\TrendAgent\TaDirectory;
use Illuminate\Http\JsonResponse;

class TaDirectoriesController extends Controller
{
    /**
     * Get directory by type, city_id, lang (from ta_directories).
     */
    public function index(IndexDirectoriesRequest $request): JsonResponse
    {
        $type = $request->input('type');
        $cityId = $request->getCityId();
        $lang = $request->getLang();

        $directory = TaDirectory::query()
            ->where('type', $type)
            ->where('city_id', $cityId)
            ->where('lang', $lang)
            ->first();

        if (! $directory) {
            return response()->json(['message' => 'Directory not found'], 404);
        }

        return response()->json([
            'data' => new DirectoryResource($directory),
            'meta' => (object) [],
        ]);
    }
}
