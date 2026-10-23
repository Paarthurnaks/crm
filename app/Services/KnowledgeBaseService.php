<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseContent;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Exception;
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

                foreach ($data['contents'] as $index => $contentData) {
                    $type = isset($contentData['file']) ? 'file' : 'text';
                    $order = $index + 1;

                    $knowledgeBaseContent = new KnowledgeBaseContent([
                        'type' => $type,
                        'content' => $type === 'text' ? $contentData['content'] : null,
                        'order' => $order,
                    ]);

                    $knowledgeBase->contents()->save($knowledgeBaseContent);

                    if (isset($contentData['file']) && $contentData['file']) {
                        $knowledgeBaseContent->addMedia($contentData['file'])->toMediaCollection('file');
                    }
                }

                return $knowledgeBase;
            });
        } catch (\Exception $e) {
            throw new Exception(self::ERROR_MESSAGES['createError']);
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
                    $knowledgeBase->clearMediaCollection('preview');
                    $knowledgeBase->addMedia($data['preview'])->toMediaCollection('preview');
                }

                if (isset($data['contents']) && $data['contents']) {
                    $existingContentIds = array_column($data['contents'], 'id');
                    $knowledgeBase->contents()->whereNotIn('id', $existingContentIds)->delete();

                    foreach ($data['contents'] as $index => $contentData) {
                        $order = $index + 1;
                        $type = isset($contentData['content']) ? 'text' : (isset($contentData['file']) ? 'file' : null);
                        $content = $contentData['content'] ?? null;

                        if (! $type) {
                            continue;
                        }

                        $knowledgeBaseContent = isset($contentData['id'])
                            ? $knowledgeBase->contents()->find($contentData['id'])
                            : new KnowledgeBaseContent;

                        if (! $knowledgeBaseContent) {
                            continue;
                        }

                        $knowledgeBaseContent->fill([
                            'type' => $type,
                            'content' => $content,
                            'order' => $order,
                        ]);

                        $knowledgeBase->contents()->save($knowledgeBaseContent);

                        if ($type === 'file' && isset($contentData['file'])) {
                            $knowledgeBaseContent->clearMediaCollection('file');
                            $knowledgeBaseContent->addMedia($contentData['file'])->toMediaCollection('file');
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            throw new Exception(self::ERROR_MESSAGES['updateError']);
        }
    }
}
