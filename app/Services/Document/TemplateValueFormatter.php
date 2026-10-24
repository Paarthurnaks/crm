<?php

namespace App\Services\Document;

use App\Enums\UserFieldTypeEnum;
use InvalidArgumentException;

class TemplateValueFormatter
{
    public function userField(string $type, mixed $value): string
    {
        if ($type === UserFieldTypeEnum::BOOLEAN->value) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Да' : 'Нет';
        }

        return $value === null || $value === '' ? 'Не указано' : $this->placeholder($value);
    }

    public function placeholder(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    /**
     * @return array<int, array<string, string>>|null
     */
    public function tableRows(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            throw new InvalidArgumentException('Некорректные данные таблицы в шаблоне');
        }

        if (! is_array($value[0]) || $value[0] === [] || array_is_list($value[0])) {
            throw new InvalidArgumentException('Некорректные данные таблицы в шаблоне');
        }

        $rows = [];
        $firstMacro = array_key_first($value[0]);

        if (! is_string($firstMacro)) {
            throw new InvalidArgumentException('Некорректные данные таблицы в шаблоне');
        }

        foreach ($value as $row) {
            if (! is_array($row) || $row === [] || array_is_list($row) || ! array_key_exists($firstMacro, $row)) {
                throw new InvalidArgumentException('Некорректные данные таблицы в шаблоне');
            }

            $formattedRow = [];

            foreach ($row as $key => $cell) {
                if (! is_string($key) || (! is_scalar($cell) && $cell !== null)) {
                    throw new InvalidArgumentException('Некорректные данные таблицы в шаблоне');
                }

                $formattedRow[$key] = $this->placeholder($cell);
            }

            $rows[] = $formattedRow;
        }

        return $rows;
    }
}
