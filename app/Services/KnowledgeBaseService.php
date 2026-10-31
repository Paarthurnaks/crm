<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class KnowledgeBaseService
{
    private const ERROR_MESSAGES = [
        'createError' => 'Произошла ошибка во время создания. Проверьте переданные вами поля',
        'updateError' => 'Произошла ошибка во время обновления. Проверьте переданные вами поля',
    ];

    /**
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public function create(array $data): KnowledgeBase
    {
        try {
            return DB::transaction(function () use ($data) {
                $knowledgeBase = KnowledgeBase::create([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                ]);

                if (isset($data['preview']) && $data['preview']) {
                    $knowledgeBase->addMedia($data['preview'])->toMediaCollection('preview');
                }

                $this->createContents($knowledgeBase, $data['contents']);
                Log::debug('knowledge_base.contents.created', ['knowledge_base_id' => $knowledgeBase->id, 'count' => count($data['contents'])]);

                return $knowledgeBase;
            });
        } catch (\Throwable $e) {
            Log::error('knowledge_base.create.failed', ['exception' => $e::class]);
            throw new RuntimeException(self::ERROR_MESSAGES['createError'], 0, $e);
        }
    }

    public function update(KnowledgeBase $knowledgeBase, array $data): void
    {
        try {
            DB::transaction(function () use ($knowledgeBase, $data) {
                $knowledgeBase->update([
                    'title' => $data['title'] ?? $knowledgeBase->title,
                    'description' => $data['description'] ?? $knowledgeBase->description,
                ]);

                if (isset($data['preview']) && $data['preview']) {
                    $this->replaceMedia($knowledgeBase, $data['preview'], 'preview');
                }

                if (isset($data['contents']) && $data['contents']) {
                    $this->updateContents($knowledgeBase, $data['contents']);
                    Log::debug('knowledge_base.contents.updated', ['knowledge_base_id' => $knowledgeBase->id, 'count' => count($data['contents'])]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('knowledge_base.update.failed', ['knowledge_base_id' => $knowledgeBase->id, 'exception' => $e::class]);
            throw new RuntimeException(self::ERROR_MESSAGES['updateError'], 0, $e);
        }
    }

    private function createContents(KnowledgeBase $knowledgeBase, array $contents): void
    {
        foreach ($contents as $index => $contentData) {
            $type = isset($contentData['file']) ? 'file' : 'text';
            $this->saveContent($knowledgeBase, new KnowledgeBaseContent, $contentData, $type, $index + 1);
        }
    }

    private function updateContents(KnowledgeBase $knowledgeBase, array $contents): void
    {
        $knowledgeBase->contents()->whereNotIn('id', array_column($contents, 'id'))->delete();

        foreach ($contents as $index => $contentData) {
            $type = isset($contentData['content']) ? 'text' : (isset($contentData['file']) ? 'file' : null);
            if (! $type) {
                continue;
            }

            $content = isset($contentData['id'])
                ? $knowledgeBase->contents()->find($contentData['id'])
                : new KnowledgeBaseContent;
            if (! $content) {
                continue;
            }

            $this->saveContent($knowledgeBase, $content, $contentData, $type, $index + 1);
        }
    }

    private function saveContent(KnowledgeBase $knowledgeBase, KnowledgeBaseContent $content, array $data, string $type, int $position): void
    {
        $content->fill([
            'type' => $type,
            'content' => $type === 'text' ? $data['content'] : null,
            'order' => $position,
        ]);
        $knowledgeBase->contents()->save($content);

        if ($type === 'file' && isset($data['file']) && $data['file']) {
            $this->replaceMedia($content, $data['file'], 'file');
        }
    }

    private function replaceMedia(KnowledgeBase|KnowledgeBaseContent $model, mixed $file, string $collection): void
    {
        $oldMedia = $model->getMedia($collection);
        $newMedia = $model->addMedia($file)->toMediaCollection($collection);
        foreach ($oldMedia as $media) {
            if ($media->id !== $newMedia->id) {
                $media->delete();
            }
        }
    }
}
