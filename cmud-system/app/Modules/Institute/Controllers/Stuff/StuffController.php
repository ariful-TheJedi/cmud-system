<?php

namespace App\Modules\Institute\Controllers\Stuff;

use App\Http\Controllers\Controller;
use App\Models\Institute\Stuff;
use App\Modules\Institute\Requests\Stuff\StuffCreateRequest;
use App\Modules\Institute\Requests\Stuff\StuffDirectoryRequest;
use App\Modules\Institute\Requests\Stuff\StuffUpdateRequest;
use App\Modules\Institute\Services\Stuff\StuffManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Handles HTTP responses for stuff directory and profile operations. */
class StuffController extends Controller
{
    /** Return the institute and assignment choices required by the create form. */
    public function options(StuffManagementService $stuffManagement): JsonResponse
    {
        return response()->json(['data' => $stuffManagement->stuffFormOptions()]);
    }

    /** Create a stuff record and return the new profile with its assignments. */
    public function store(StuffCreateRequest $request, StuffManagementService $stuffManagement): JsonResponse
    {
        return response()->json([
            'data' => $stuffManagement->createStuff(
                $request->validated(),
                [
                    'nid_file' => $request->file('nid_file'),
                    'photo' => $request->file('photo'),
                ],
            ),
            'message' => 'Stuff member created.',
        ], 201);
    }

    /** Return a filtered stuff directory with totals needed by the stuff page header. */
    public function index(StuffDirectoryRequest $request, StuffManagementService $stuff): JsonResponse
    {
        $result = $stuff->stuffRecords($request->validated());
        $paginator = $result['stuff'];

        return response()->json([
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total_stuff' => $result['total_stuff'],
                'branch_totals' => $result['branch_totals'],
            ],
        ]);
    }

    /** Return an individual stuff profile and its branch and department assignments. */
    public function show(Stuff $stuff, StuffManagementService $stuffManagement): JsonResponse
    {
        return response()->json(['data' => $stuffManagement->stuffInformation($stuff->id)]);
    }

    /** Stream the NID document as an attachment instead of opening it in the browser. */
    public function downloadNid(Stuff $stuff): StreamedResponse
    {
        abort_unless(
            filled($stuff->nid_file_path) && Storage::disk('public')->exists($stuff->nid_file_path),
            404,
        );

        $extension = pathinfo($stuff->nid_file_path, PATHINFO_EXTENSION);
        $filename = filled($extension) ? "nid-document.{$extension}" : 'nid-document';

        return Storage::disk('public')->download($stuff->nid_file_path, $filename);
    }

    /** Persist validated profile changes and return the refreshed stuff profile. */
    public function update(StuffUpdateRequest $request, Stuff $stuff, StuffManagementService $stuffManagement): JsonResponse
    {
        return response()->json([
            'data' => $stuffManagement->updateStuffInformation(
                $stuff->id,
                $request->validated(),
                [
                    'nid_file' => $request->file('nid_file'),
                    'photo' => $request->file('photo'),
                ],
                [
                    'nid_file' => $request->boolean('remove_nid_file'),
                    'photo' => $request->boolean('remove_photo'),
                ],
            ),
            'message' => 'Stuff information updated.',
        ]);
    }
}
