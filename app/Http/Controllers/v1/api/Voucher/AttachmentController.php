<?php

namespace App\Http\Controllers\v1\api\Voucher;

use App\Http\Controllers\v1\api\BaseApiController;
use App\Http\Resources\Voucher\DocumentAttachmentResource;
use App\Models\Vouchers\DocumentAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentController extends BaseApiController
{
    protected array $permissions = [
        'index'     => 'vouchers.*.show',
        'show'      => 'vouchers.*.show',
        'upload'    => 'vouchers.*.create',
        'download'  => 'vouchers.*.show',
        'destroy'   => 'vouchers.*.destroy',
    ];

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'document_type' => 'required|string',
            'document_id' => 'required|integer',
            'file' => 'required|file|max:10240',
        ]);

        $this->authorizeAction($request);

        $file = $request->file('file');
        $hash = md5_file($file->getRealPath());

        $existing = DocumentAttachment::byHash($hash)
            ->where('document_type', $request->document_type)
            ->where('document_id', $request->document_id)
            ->first();

        if ($existing) {
            return $this->success(
                new DocumentAttachmentResource($existing),
                message: 'File already attached.'
            );
        }

        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $storedName = Str::random(40) . '.' . $extension;
        $path = $file->storeAs('attachments/' . date('Y/m'), $storedName, 'public');

        $attachment = DocumentAttachment::create([
            'document_type' => $request->document_type,
            'document_id' => $request->document_id,
            'file_name' => $originalName,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'hash' => $hash,
            'uploaded_by' => auth()->id(),
        ]);

        return $this->created(
            new DocumentAttachmentResource($attachment),
            'File uploaded successfully.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'document_type' => 'required|string',
            'document_id' => 'required|integer',
        ]);

        $this->authorizeAction($request);

        $attachments = DocumentAttachment::where('document_type', $request->document_type)
            ->where('document_id', $request->document_id)
            ->latest()
            ->get();

        return $this->success(DocumentAttachmentResource::collection($attachments));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $attachment = DocumentAttachment::findOrFail($id);
        $this->authorizeAction($request, $attachment);

        return $this->success(new DocumentAttachmentResource($attachment));
    }

    public function download(int $id)
    {
        $attachment = DocumentAttachment::findOrFail($id);

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json(['message' => 'File not found on storage.'], 404);
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $attachment = DocumentAttachment::findOrFail($id);

        $this->authorizeAction($request, $attachment);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return $this->deleted('Attachment deleted successfully.');
    }
}
