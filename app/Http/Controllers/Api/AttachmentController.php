<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\HasEvidence;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Expense;
use App\Models\Revenue;
use App\Services\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ຫຼັກຖານການຈ່າຍ/ຮັບ — docs/architecture.md ພາກ 4.4.
 */
final class AttachmentController extends Controller
{
    public function __construct(private AttachmentService $attachments) {}

    public function indexForExpense(Expense $expense): AnonymousResourceCollection
    {
        return $this->listFor($expense, 'view', $expense);
    }

    public function indexForRevenue(Revenue $revenue): AnonymousResourceCollection
    {
        return $this->listFor($revenue, 'view', $revenue);
    }

    public function storeForExpense(StoreAttachmentRequest $request, Expense $expense): JsonResponse
    {
        return $this->store($request, $expense);
    }

    public function storeForRevenue(StoreAttachmentRequest $request, Revenue $revenue): JsonResponse
    {
        return $this->store($request, $revenue);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $this->attachments->detach($attachment);

        return response()->json(status: 204);
    }

    /**
     * Stream ຮູບເຕັມ. Route ນີ້ມີ middleware 'signed' ແລະ **ບໍ່ມີ** auth —
     * ລາຍເຊັນຄືສິດເຂົ້າເຖິງ ເພື່ອໃຫ້ <img src> ຂອງ browser ໃຊ້ໄດ້ໂດຍກົງ
     * (browser ບໍ່ສົ່ງ header auth ໄປກັບ <img>).
     *
     * ໝາຍເຫດ production: ຖ້າຍ້າຍໄປ S3/R2 ໃຫ້ໃຊ້ Storage::temporaryUrl() ແທນ
     * ເພື່ອບໍ່ໃຫ້ traffic ໄຫຼຜ່ານ PHP.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        return $this->stream($attachment, $attachment->path, $attachment->mime_type);
    }

    public function thumbnail(Attachment $attachment): StreamedResponse
    {
        abort_if($attachment->thumbnail_path === null, 404);

        return $this->stream($attachment, $attachment->thumbnail_path, 'image/webp');
    }

    private function listFor(HasEvidence $attachable, string $ability, HasEvidence $target): AnonymousResourceCollection
    {
        $this->authorize($ability, $target);

        return AttachmentResource::collection(
            $attachable->attachments()->with('uploader')->latest('id')->get()
        );
    }

    /** @param  Model&HasEvidence  $attachable */
    private function store(StoreAttachmentRequest $request, HasEvidence $attachable): JsonResponse
    {
        $kind = (string) $request->validated('kind');
        $created = [];

        foreach ($request->file('files') as $file) {
            $created[] = $this->attachments->attach(
                $attachable,
                $file,
                $kind,
                (int) $request->user()->id,
            );
        }

        return AttachmentResource::collection($created)->response()->setStatusCode(201);
    }

    private function stream(Attachment $attachment, string $path, string $mime): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $attachment->original_name, [
            'Content-Type' => $mime,
            // ກັນ browser ຕີຄວາມໄຟລ໌ເປັນ HTML → stored XSS
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
