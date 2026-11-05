<?php
declare(strict_types=1);
namespace App\Modules\Vocabulary\Repositories;
final class WordRepository
{
    // Giữ hợp đồng của DictionaryWordController; dùng cùng khóa/trùng với CSV và Admin.
    public function create(array $word, array $source): int
    {
        return (new ContentRepository())->saveWord(null, $word, $source);
    }
}
